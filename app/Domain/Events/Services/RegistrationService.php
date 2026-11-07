<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Entries into events (LLD 06 §5). Every decision that can approve runs
 * under a lock on the event row, so capacity is never exceeded.
 */
final class RegistrationService
{
    public function __construct(
        private readonly EntryRules $rules,
        private readonly TeamMemberService $teamMembers,
    ) {}

    /**
     * A team applies; the entry waits for an organiser's decision.
     *
     * @param  list<int>  $memberIds
     */
    public function apply(Event $event, EventCategory $category, Team $team, array $memberIds, User $by): EventRegistration
    {
        $this->assertCategoryOf($event, $category);

        return DB::transaction(function () use ($event, $category, $team, $memberIds, $by): EventRegistration {
            $locked = $this->lock($event);
            $violations = $this->rules->check($locked, $category, $team, $this->users($memberIds), EntryContext::apply());

            if ($violations !== []) {
                throw $this->violation($violations);
            }

            $registration = $this->createEntry($locked, $category, $team, $memberIds, RegistrationSource::Applied, RegistrationStatus::Pending, $by);
            $this->log($registration, $by, 'registration.applied');

            return $registration;
        });
    }

    /**
     * An organiser adds a team: as an invitation the team accepts, or as a
     * direct entry that is approved (or waitlisted) straight away.
     *
     * @param  list<int>  $memberIds
     */
    public function addByOrganiser(Event $event, EventCategory $category, Team $team, array $memberIds, bool $asInvite, ?string $overrideReason, User $by): EventRegistration
    {
        $this->assertCategoryOf($event, $category);

        return DB::transaction(function () use ($event, $category, $team, $memberIds, $asInvite, $overrideReason, $by): EventRegistration {
            $locked = $this->lock($event);

            if ($asInvite) {
                $violations = $this->rules->check($locked, $category, $team, $this->users($memberIds), EntryContext::add());
                $override = $this->resolveOverride($locked, $violations, $overrideReason, $by);
                $registration = $this->createEntry($locked, $category, $team, $memberIds, RegistrationSource::Organiser, RegistrationStatus::Invited, $by);
                $registration->forceFill($override)->save();
                $this->log($registration, $by, 'registration.invited');

                return $registration;
            }

            $registration = $this->createEntry($locked, $category, $team, $memberIds, RegistrationSource::Organiser, RegistrationStatus::Pending, $by);

            return $this->decide($locked, $registration, $overrideReason, $by, EntryContext::add()->excluding($registration->getKey()));
        });
    }

    /**
     * The team accepts an organiser's invitation, confirming its members.
     *
     * @param  list<int>  $memberIds
     */
    public function acceptInvite(EventRegistration $registration, array $memberIds, User $by): EventRegistration
    {
        return DB::transaction(function () use ($registration, $memberIds, $by): EventRegistration {
            $event = $this->lock($registration->event);
            $registration = $this->refresh($registration);
            $this->assertStatus($registration, RegistrationStatus::Invited);
            $registration->members()->sync($memberIds);

            return $this->decide($event, $registration, $registration->override_reason, $by, EntryContext::approve($registration->getKey()), trusted: $registration->override_reason !== null);
        });
    }

    public function approve(EventRegistration $registration, ?string $overrideReason, User $by): EventRegistration
    {
        return DB::transaction(function () use ($registration, $overrideReason, $by): EventRegistration {
            $event = $this->lock($registration->event);

            return $this->decide($event, $this->refresh($registration), $overrideReason, $by, EntryContext::approve($registration->getKey()));
        });
    }

    public function reject(EventRegistration $registration, string $reason, User $by): void
    {
        DB::transaction(function () use ($registration, $reason, $by): void {
            $registration = $this->refresh($registration);
            $registration->transitionTo(RegistrationStatus::Rejected);
            $registration->forceFill([
                'decision_note' => $reason,
                'reviewed_by' => $by->getKey(),
                'reviewed_at' => now(),
                'waitlist_position' => null,
            ])->save();

            $this->log($registration, $by, 'registration.rejected', ['reason' => $reason]);
        });
    }

    public function waitlist(EventRegistration $registration, User $by): void
    {
        DB::transaction(function () use ($registration, $by): void {
            $event = $this->lock($registration->event);
            $registration = $this->refresh($registration);
            $registration->transitionTo(RegistrationStatus::Waitlisted);
            $registration->forceFill([
                'waitlist_position' => $this->nextWaitlistPosition($event),
                'reviewed_by' => $by->getKey(),
                'reviewed_at' => now(),
            ])->save();

            $this->log($registration, $by, 'registration.waitlisted');
        });
    }

    /**
     * Teams may withdraw until registration closes; organisers at any time.
     * When an approved entry leaves, the waitlist moves up (if enabled).
     */
    public function withdraw(EventRegistration $registration, User $by, bool $byOrganiser = false): void
    {
        DB::transaction(function () use ($registration, $by, $byOrganiser): void {
            $event = $this->lock($registration->event);
            $registration = $this->refresh($registration);
            $closesAt = $event->registration_closes_at ?? $event->starts_at;

            if (! $byOrganiser && now()->greaterThan($closesAt)) {
                throw DomainRuleViolation::with(['registration' => __('events.errors.withdraw_closed')]);
            }

            $wasApproved = $registration->status === RegistrationStatus::Approved;
            $registration->transitionTo(RegistrationStatus::Withdrawn);
            $registration->forceFill(['withdrawn_at' => now(), 'waitlist_position' => null])->save();

            $this->log($registration, $by, 'registration.withdrawn', ['by_organiser' => $byOrganiser]);

            if ($wasApproved && self::autoPromotes()) {
                $this->promoteFromWaitlist($event, $by);
            }
        });
    }

    /**
     * Approve the waitlisted entry with the lowest position that still passes
     * the rules and fits the event and category capacity.
     */
    public function promoteFromWaitlist(Event $event, User $by): ?EventRegistration
    {
        return DB::transaction(function () use ($event, $by): ?EventRegistration {
            $locked = $this->lock($event);

            if (! $locked->hasCapacity()) {
                return null;
            }

            $waiting = $locked->registrations()
                ->where('status', RegistrationStatus::Waitlisted)
                ->orderBy('waitlist_position')
                ->orderBy('id')
                ->with(['category', 'team', 'members'])
                ->get();

            foreach ($waiting as $registration) {
                if ($registration->category->isFull()) {
                    continue;
                }

                $violations = $this->rules->check($locked, $registration->category, $registration->team, $registration->members->all(), EntryContext::approve($registration->getKey()));

                if ($violations !== []) {
                    continue;
                }

                $this->markApproved($locked, $registration, $by);
                $this->log($registration, $by, 'registration.promoted');

                return $registration;
            }

            return null;
        });
    }

    public static function autoPromotes(): bool
    {
        return (bool) (config('platform.settings')['events.auto_promote_waitlist'] ?? true);
    }

    /**
     * Rules, override, then capacity: approved, or waitlisted when full.
     */
    private function decide(Event $event, EventRegistration $registration, ?string $overrideReason, User $by, EntryContext $context, bool $trusted = false): EventRegistration
    {
        $registration->loadMissing(['category', 'team', 'members']);
        $violations = $this->rules->check($event, $registration->category, $registration->team, $registration->members->all(), $context);

        $override = $trusted && $violations !== []
            ? ['override_reason' => $overrideReason, 'override_violations' => $this->codes($violations)]
            : $this->resolveOverride($event, $violations, $overrideReason, $by);

        $registration->forceFill($override);

        $full = ! $event->hasCapacity() || $registration->category->isFull();

        if ($full) {
            if ($registration->status === RegistrationStatus::Waitlisted) {
                throw DomainRuleViolation::with(['registration' => __('events.errors.still_full')]);
            }

            $registration->transitionTo(RegistrationStatus::Waitlisted);
            $registration->forceFill([
                'waitlist_position' => $this->nextWaitlistPosition($event),
                'reviewed_by' => $by->getKey(),
                'reviewed_at' => now(),
            ])->save();

            $this->log($registration, $by, 'registration.waitlisted');

            return $registration;
        }

        $this->markApproved($event, $registration, $by);
        $this->log($registration, $by, 'registration.approved', $override === [] ? [] : ['override' => $override]);

        return $registration;
    }

    private function markApproved(Event $event, EventRegistration $registration, User $by): void
    {
        $registration->transitionTo(RegistrationStatus::Approved);
        $registration->forceFill([
            'members_snapshot' => $this->snapshot($event, $registration),
            'waitlist_position' => null,
            'reviewed_by' => $by->getKey(),
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * Without violations: nothing. With violations: a reason from someone
     * holding events.approve in the event's department, or a refusal.
     *
     * @param  list<RuleViolation>  $violations
     * @return array{override_reason?: string, override_violations?: list<string>}
     */
    private function resolveOverride(Event $event, array $violations, ?string $reason, User $by): array
    {
        if ($violations === []) {
            return [];
        }

        $event->loadMissing('department');

        if (filled($reason) && app(DepartmentAccess::class)->can($by, 'events.approve', $event->department)) {
            return ['override_reason' => (string) $reason, 'override_violations' => $this->codes($violations)];
        }

        throw $this->violation($violations);
    }

    /**
     * @return list<array{user_id: int, name: string, age: int|null, role: string|null}>
     */
    private function snapshot(Event $event, EventRegistration $registration): array
    {
        $startDate = $event->localStartDate();

        return $registration->members()->orderBy('users.id')->get()
            ->map(fn (User $member): array => [
                'user_id' => $member->getKey(),
                'name' => $member->name_en,
                'age' => $member->ageOn($startDate),
                'role' => $this->teamMembers->roleOf($registration->team, $member)?->value,
            ])
            ->all();
    }

    /**
     * @param  list<int>  $memberIds
     */
    private function createEntry(Event $event, EventCategory $category, Team $team, array $memberIds, RegistrationSource $source, RegistrationStatus $status, User $by): EventRegistration
    {
        $registration = EventRegistration::query()->create([
            'event_id' => $event->getKey(),
            'category_id' => $category->getKey(),
            'team_id' => $team->getKey(),
            'source' => $source,
            'status' => $status,
            'applied_by' => $by->getKey(),
        ]);

        $registration->members()->sync(array_values(array_unique($memberIds)));

        return $registration;
    }

    private function lock(Event $event): Event
    {
        return Event::query()->lockForUpdate()->findOrFail($event->getKey());
    }

    private function refresh(EventRegistration $registration): EventRegistration
    {
        return EventRegistration::query()->lockForUpdate()->findOrFail($registration->getKey());
    }

    private function nextWaitlistPosition(Event $event): int
    {
        return (int) $event->registrations()->max('waitlist_position') + 1;
    }

    private function assertCategoryOf(Event $event, EventCategory $category): void
    {
        if ($category->event_id !== $event->getKey()) {
            throw DomainRuleViolation::with(['category_id' => __('events.errors.category_mismatch')]);
        }
    }

    private function assertStatus(EventRegistration $registration, RegistrationStatus $status): void
    {
        if ($registration->status !== $status) {
            throw DomainRuleViolation::with(['registration' => __('events.errors.not_invited')]);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<User>
     */
    private function users(array $ids): array
    {
        return User::query()->whereKey(array_values(array_unique($ids)))->get()->all();
    }

    /**
     * @param  list<RuleViolation>  $violations
     * @return list<string>
     */
    private function codes(array $violations): array
    {
        return array_map(fn (RuleViolation $violation): string => $violation->code, $violations);
    }

    /**
     * @param  list<RuleViolation>  $violations
     */
    private function violation(array $violations): DomainRuleViolation
    {
        return DomainRuleViolation::with(['registration' => array_map(fn (RuleViolation $violation): string => $violation->message, $violations)]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(EventRegistration $registration, User $by, string $event, array $properties = []): void
    {
        activity('events')->performedOn($registration)->causedBy($by)
            ->withProperties(array_merge([
                'event_id' => $registration->event_id,
                'team_id' => $registration->team_id,
                'category_id' => $registration->category_id,
                'status' => $registration->status->value,
            ], $properties))
            ->event($event)
            ->log(ucfirst(str_replace(['registration.', '_'], ['Registration ', ' '], $event)));
    }
}
