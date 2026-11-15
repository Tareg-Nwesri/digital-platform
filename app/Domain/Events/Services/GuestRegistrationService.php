<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Teams\Services\TeamService;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Services\RobotService;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A visitor registers a new team for an open public event. The team is
 * created pending, with the contact person as captain and the members as
 * offline members; the entry waits for the organisers. Approving it
 * activates the team (RegistrationService::approve).
 */
final class GuestRegistrationService
{
    public function __construct(
        private readonly TeamService $teams,
        private readonly TeamMemberService $members,
        private readonly RobotService $robots,
        private readonly EntryRules $rules,
    ) {}

    /**
     * @param  array{team_name: string, city: string, contact_name: string, contact_email: string, contact_phone: string, robot_name?: string|null, members: list<array{name: string, birthdate: string}>}  $data
     */
    public function register(Event $event, EventCategory $category, array $data): EventRegistration
    {
        if ($category->event_id !== $event->getKey()) {
            throw DomainRuleViolation::with(['category_id' => __('events.errors.category_mismatch')]);
        }

        return DB::transaction(function () use ($event, $category, $data): EventRegistration {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $teamName = Str::squish($data['team_name']);

            if ($this->nameTaken($locked, $teamName)) {
                throw DomainRuleViolation::with(['team_name' => __('events.errors.team_name_registered')]);
            }

            $team = $this->teams->create([
                'name' => $teamName,
                'location' => Str::squish($data['city']),
                'department_id' => $locked->department_id,
            ], approved: false);

            $contact = $this->contact($data);
            $this->members->join($team, $contact, TeamRole::Captain, $contact);

            $memberIds = [];

            foreach ($data['members'] as $member) {
                $name = Str::squish($member['name']);
                $memberIds[] = $this->members->addWithoutLogin($team, [
                    'name_en' => $name,
                    'name_ar' => $name,
                    'birthdate' => $member['birthdate'],
                ], TeamRole::Member, $contact)->getKey();
            }

            $violations = $this->rules->check($locked, $category, $team, User::query()->whereKey($memberIds)->get()->all(), EntryContext::guest());

            if ($violations !== []) {
                throw DomainRuleViolation::with(['registration' => implode(' ', array_map(fn (RuleViolation $violation): string => $violation->message, $violations))]);
            }

            $registration = EventRegistration::query()->create([
                'event_id' => $locked->getKey(),
                'category_id' => $category->getKey(),
                'team_id' => $team->getKey(),
                'source' => RegistrationSource::Public,
                'status' => RegistrationStatus::Pending,
                'applied_by' => $contact->getKey(),
            ]);
            $registration->members()->sync($memberIds);

            if (filled($data['robot_name'] ?? null)) {
                $robot = $this->robots->create($team, ['name' => Str::squish((string) $data['robot_name']), 'status' => RobotStatus::Building->value], $contact);
                $registration->robots()->attach($robot->getKey());
            }

            activity('events')->performedOn($registration)->causedBy($contact)
                ->withProperties(['event_id' => $locked->getKey(), 'team_id' => $team->getKey(), 'category_id' => $category->getKey(), 'status' => RegistrationStatus::Pending->value])
                ->event('registration.public')
                ->log('Registration from the public site');

            return $registration;
        });
    }

    /**
     * The contact person becomes the captain. A known email reuses that
     * account; otherwise a login without a password is created, which the
     * person activates through "forgot password" once the team is approved.
     *
     * @param  array{contact_name: string, contact_email: string, contact_phone: string}  $data
     */
    private function contact(array $data): User
    {
        $email = Str::lower(trim($data['contact_email']));
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->phone === null) {
                $existing->forceFill(['phone' => trim($data['contact_phone'])])->save();
            }

            return $existing;
        }

        $name = Str::squish($data['contact_name']);

        return User::query()->create([
            'name_en' => $name,
            'name_ar' => $name,
            'email' => $email,
            'phone' => trim($data['contact_phone']),
            'password' => null,
            'can_login' => true,
            'locale' => app()->getLocale(),
        ]);
    }

    private function nameTaken(Event $event, string $name): bool
    {
        return $event->registrations()->active()
            ->whereHas('team', fn ($query) => $query->whereRaw('LOWER(name) = ?', [Str::lower($name)]))
            ->exists();
    }
}
