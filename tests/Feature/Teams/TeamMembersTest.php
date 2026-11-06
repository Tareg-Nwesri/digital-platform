<?php

use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->members = app(TeamMemberService::class);
    $this->team = Team::factory()->create();
    $this->captain = User::factory()->create();
    $this->members->join($this->team, $this->captain, TeamRole::Captain);
});

it('gives the member a role scoped to their team only', function () {
    $other = Team::factory()->create();
    $coach = User::factory()->create();
    $this->members->join($this->team, $coach, TeamRole::Coach);

    $context = app(WorkspaceContext::class);
    $context->set($this->team->workspace, $this->team);
    expect($coach->fresh()->can('members.invite'))->toBeTrue();

    $context->set($other->workspace, $other);
    expect($coach->fresh()->can('members.invite'))->toBeFalse()
        ->and($this->members->roleOf($other, $coach))->toBeNull();
});

it('refuses to add members beyond the members.max feature', function () {
    app(TeamFeatures::class)->override($this->team, TeamFeature::MembersMax, 2);
    $this->members->join($this->team, User::factory()->create(), TeamRole::Member);

    $this->members->join($this->team, User::factory()->create(), TeamRole::Member);
})->throws(DomainRuleViolation::class);

it('allows only one captain and swaps roles when captaincy is transferred', function () {
    $coach = User::factory()->create();
    $this->members->join($this->team, $coach, TeamRole::Coach);

    expect(fn () => $this->members->join($this->team, User::factory()->create(), TeamRole::Captain))
        ->toThrow(DomainRuleViolation::class)
        ->and(fn () => $this->members->changeRole($this->team, $coach, TeamRole::Captain))
        ->toThrow(DomainRuleViolation::class);

    $this->members->transferCaptaincy($this->team, $coach);

    expect($this->members->roleOf($this->team, $coach))->toBe(TeamRole::Captain)
        ->and($this->members->roleOf($this->team, $this->captain))->toBe(TeamRole::Coach)
        ->and($this->members->captain($this->team)->is($coach))->toBeTrue();
});

it('limits members without a login to member and viewer roles', function () {
    $student = $this->members->addWithoutLogin($this->team, ['name_en' => 'Young Builder', 'birthdate' => '2014-03-01'], TeamRole::Member);

    expect($student->can_login)->toBeFalse()
        ->and($student->email)->toBeNull()
        ->and($this->team->hasMember($student))->toBeTrue()
        ->and(fn () => $this->members->changeRole($this->team, $student, TeamRole::Coach))
        ->toThrow(DomainRuleViolation::class);
});

it('cannot remove the captain, and removing a member revokes their role', function () {
    $member = User::factory()->create();
    $this->members->join($this->team, $member, TeamRole::Member);

    expect(fn () => $this->members->remove($this->team, $this->captain))->toThrow(DomainRuleViolation::class);

    $this->members->remove($this->team, $member);

    expect($this->team->hasMember($member))->toBeFalse()
        ->and($this->members->roleOf($this->team, $member))->toBeNull();
});

it('logs membership changes to the activity log', function () {
    expect(Activity::query()->where('event', 'member.joined')->where('subject_id', $this->team->id)->exists())->toBeTrue();
});
