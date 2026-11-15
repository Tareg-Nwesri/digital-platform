<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\Robot;
use App\Models\User;

/**
 * Entry rules for one team in one category (LLD 06 §4). Pure: it reads but
 * never writes. Capacity is not a rule; it decides approved or waitlisted.
 */
final class EntryRules
{
    public function __construct(private readonly TeamFeatures $features) {}

    /**
     * @param  list<User>  $members
     * @param  list<Robot>  $robots
     * @return list<RuleViolation>
     */
    public function check(Event $event, EventCategory $category, Team $team, array $members, EntryContext $context, array $robots = []): array
    {
        $violations = [];

        if ($team->status !== TeamStatus::Active && ! $context->isGuest()) {
            $violations[] = RuleViolation::of('team_inactive');
        }

        if ($context->isApply() || $context->isGuest()) {
            if ($context->isApply() && ! $this->features->active($team, TeamFeature::EventsRegister)) {
                $violations[] = RuleViolation::of('feature_off');
            }

            if ($event->visibility === EventVisibility::InviteOnly && ! $event->hasInvited($team)) {
                $violations[] = RuleViolation::of('not_visible');
            }

            if (! $event->isAcceptingApplications($context->now)) {
                $violations[] = RuleViolation::of('window_closed');
            }
        } elseif (! $event->status->acceptsOrganiserEntries()) {
            $violations[] = RuleViolation::of('event_closed');
        }

        if ($this->hasActiveEntry($category, $team, $context->excludeRegistrationId)) {
            $violations[] = RuleViolation::of('duplicate');
        }

        return [
            ...$violations,
            ...$this->memberViolations($event, $category, $team, $members),
            ...$this->robotViolations($category, $team, $robots, $context),
        ];
    }

    /**
     * Applying teams must bring between one robot and the category maximum.
     * Organisers may leave robots out; when they name robots, the same rules
     * apply. Guest registrations are checked by the organisers on approval.
     *
     * @param  list<Robot>  $robots
     * @return list<RuleViolation>
     */
    private function robotViolations(EventCategory $category, Team $team, array $robots, EntryContext $context): array
    {
        if ($context->isGuest() || $category->robots_per_team < 1 || ($robots === [] && ! $context->isApply())) {
            return [];
        }

        $violations = [];
        $count = count($robots);

        if ($count < 1 || $count > $category->robots_per_team) {
            $violations[] = RuleViolation::of('robots_count', ['max' => $category->robots_per_team]);
        }

        $outsiders = array_filter($robots, fn (Robot $robot): bool => $robot->team_id !== $team->getKey());

        if ($outsiders !== []) {
            $violations[] = RuleViolation::of('robot_not_in_team', ['names' => $this->robotNames($outsiders)]);
        }

        $retired = array_filter($robots, fn (Robot $robot): bool => $robot->status === RobotStatus::Retired);

        if ($retired !== []) {
            $violations[] = RuleViolation::of('robot_retired', ['names' => $this->robotNames($retired)]);
        }

        $limits = array_filter([
            'weight_g' => $category->max_weight_g,
            'length_mm' => $category->max_length_mm,
            'width_mm' => $category->max_width_mm,
            'height_mm' => $category->max_height_mm,
        ], fn (?int $limit): bool => $limit !== null);

        if ($limits === []) {
            return $violations;
        }

        $unmeasured = array_filter($robots, fn (Robot $robot): bool => collect($limits)->keys()->contains(fn (string $field): bool => $robot->{$field} === null));

        if ($unmeasured !== []) {
            $violations[] = RuleViolation::of('robot_measurements', ['names' => $this->robotNames($unmeasured)]);
        }

        $heavy = array_filter($robots, fn (Robot $robot): bool => isset($limits['weight_g']) && $robot->weight_g !== null && $robot->weight_g > $limits['weight_g']);

        if ($heavy !== []) {
            $violations[] = RuleViolation::of('robot_weight', ['names' => $this->robotNames($heavy), 'max' => $limits['weight_g']]);
        }

        $sizes = array_diff_key($limits, ['weight_g' => true]);
        $big = array_filter($robots, fn (Robot $robot): bool => collect($sizes)->contains(fn (int $limit, string $field): bool => $robot->{$field} !== null && $robot->{$field} > $limit));

        if ($big !== []) {
            $violations[] = RuleViolation::of('robot_size', ['names' => $this->robotNames($big)]);
        }

        return $violations;
    }

    /**
     * @param  array<Robot>  $robots
     */
    private function robotNames(array $robots): string
    {
        return implode(', ', array_map(fn (Robot $robot): string => $robot->name, $robots));
    }

    /**
     * @param  list<User>  $members
     * @return list<RuleViolation>
     */
    private function memberViolations(Event $event, EventCategory $category, Team $team, array $members): array
    {
        $violations = [];
        $count = count($members);

        if ($count < $category->members_min || $count > $category->members_max) {
            $violations[] = RuleViolation::of('members_count', ['min' => $category->members_min, 'max' => $category->members_max]);
        }

        $teamMemberIds = $team->members()->pluck('users.id')->all();
        $outsiders = array_filter($members, fn (User $member): bool => ! in_array($member->getKey(), $teamMemberIds, true));

        if ($outsiders !== []) {
            $violations[] = RuleViolation::of('member_not_in_team', ['names' => $this->names($outsiders)]);
        }

        if (! $category->hasAgeLimits()) {
            return $violations;
        }

        $missing = array_filter($members, fn (User $member): bool => $member->birthdate === null);

        if ($missing !== []) {
            $violations[] = RuleViolation::of('member_no_birthdate', ['names' => $this->names($missing)]);
        }

        $startDate = $event->localStartDate();
        $outOfRange = array_filter($members, function (User $member) use ($category, $startDate): bool {
            $age = $member->ageOn($startDate);

            return $age !== null
                && (($category->age_min !== null && $age < $category->age_min)
                    || ($category->age_max !== null && $age > $category->age_max));
        });

        if ($outOfRange !== []) {
            $violations[] = RuleViolation::of('member_age', [
                'names' => $this->names($outOfRange),
                'min' => $category->age_min ?? 0,
                'max' => $category->age_max ?? 99,
            ]);
        }

        return $violations;
    }

    private function hasActiveEntry(EventCategory $category, Team $team, ?int $excludeId): bool
    {
        return EventRegistration::query()
            ->active()
            ->where('category_id', $category->getKey())
            ->where('team_id', $team->getKey())
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->exists();
    }

    /**
     * @param  array<User>  $members
     */
    private function names(array $members): string
    {
        return implode(', ', array_map(fn (User $member): string => $member->name, $members));
    }
}
