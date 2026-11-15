<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Work\Models\Robot;
use App\Models\User;
use App\Support\Concerns\HasStatusTransitions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int $category_id
 * @property int $team_id
 * @property RegistrationSource $source
 * @property RegistrationStatus $status
 * @property list<array{user_id: int, name: string, age: int|null, role: string|null}>|null $members_snapshot
 * @property int|null $applied_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $decision_note
 * @property string|null $override_reason
 * @property list<string>|null $override_violations
 * @property int|null $waitlist_position
 * @property Carbon|null $withdrawn_at
 * @property-read Event $event
 * @property-read EventCategory $category
 * @property-read Team $team
 */
#[Fillable([
    'event_id', 'category_id', 'team_id', 'source', 'status', 'members_snapshot', 'applied_by', 'reviewed_by',
    'reviewed_at', 'decision_note', 'override_reason', 'override_violations', 'waitlist_position', 'withdrawn_at',
])]
class EventRegistration extends Model
{
    use HasStatusTransitions;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RegistrationSource::class,
            'status' => RegistrationStatus::class,
            'members_snapshot' => 'array',
            'override_violations' => 'array',
            'reviewed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'waitlist_position' => 'integer',
        ];
    }

    /**
     * @return array<string, list<RegistrationStatus>>
     */
    public static function allowedTransitions(): array
    {
        return [
            RegistrationStatus::Invited->value => [RegistrationStatus::Approved, RegistrationStatus::Waitlisted, RegistrationStatus::Withdrawn],
            RegistrationStatus::Pending->value => [RegistrationStatus::Approved, RegistrationStatus::Waitlisted, RegistrationStatus::Rejected, RegistrationStatus::Withdrawn],
            RegistrationStatus::Waitlisted->value => [RegistrationStatus::Approved, RegistrationStatus::Withdrawn],
            RegistrationStatus::Approved->value => [RegistrationStatus::Withdrawn],
            RegistrationStatus::Rejected->value => [],
            RegistrationStatus::Withdrawn->value => [],
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<EventCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Members chosen when applying or adding; the snapshot is built from them.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'registration_members', 'registration_id');
    }

    /**
     * @return BelongsToMany<Robot, $this>
     */
    public function robots(): BelongsToMany
    {
        return $this->belongsToMany(Robot::class, 'registration_robot', 'registration_id');
    }

    /**
     * @return HasOne<EventResult, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(EventResult::class, 'registration_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<EventRegistration>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', RegistrationStatus::active());
    }
}
