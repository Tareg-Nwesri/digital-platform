<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Organisation\Models\Department;
use App\Domain\Teams\Models\Team;
use App\Support\Concerns\HasLocalizedFields;
use App\Support\Concerns\HasStatusTransitions;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $department_id
 * @property string $slug
 * @property string $title_en
 * @property string|null $title_ar
 * @property string $timezone
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $registration_opens_at
 * @property Carbon|null $registration_closes_at
 * @property EventStatus $status
 * @property EventVisibility $visibility
 * @property int|null $capacity
 * @property Carbon|null $results_published_at
 * @property int|null $created_by
 * @property-read Department $department
 */
#[Fillable([
    'department_id', 'slug', 'title_en', 'title_ar', 'description_en', 'description_ar', 'rules_en', 'rules_ar',
    'terms_en', 'terms_ar', 'venue', 'city', 'timezone', 'starts_at', 'ends_at', 'registration_opens_at',
    'registration_closes_at', 'status', 'visibility', 'capacity', 'results_published_at', 'created_by',
])]
#[UseFactory(EventFactory::class)]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasLocalizedFields, HasStatusTransitions, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'results_published_at' => 'datetime',
            'status' => EventStatus::class,
            'visibility' => EventVisibility::class,
            'capacity' => 'integer',
        ];
    }

    /**
     * @return array<string, list<EventStatus>>
     */
    public static function allowedTransitions(): array
    {
        return [
            EventStatus::Draft->value => [EventStatus::Open],
            EventStatus::Open->value => [EventStatus::Closed],
            EventStatus::Closed->value => [EventStatus::Open, EventStatus::Running],
            EventStatus::Running->value => [EventStatus::Finished],
            EventStatus::Finished->value => [EventStatus::Archived],
            EventStatus::Archived->value => [],
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<EventCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(EventCategory::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * Teams invited to an invite-only event.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function invitedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'event_invitations')
            ->withPivot('invited_by')
            ->withTimestamps();
    }

    /**
     * @return HasManyThrough<EventResult, EventRegistration, $this>
     */
    public function results(): HasManyThrough
    {
        return $this->hasManyThrough(EventResult::class, EventRegistration::class, 'event_id', 'registration_id');
    }

    /**
     * Shown on the public site (SRS C-07): public and past draft, or invite-only
     * once its results are published.
     *
     * @param  Builder<Event>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $public) => $public
                ->where('visibility', EventVisibility::Public)
                ->where('status', '!=', EventStatus::Draft))
            ->orWhere(fn (Builder $private) => $private
                ->where('visibility', EventVisibility::InviteOnly)
                ->whereNotNull('results_published_at')));
    }

    /**
     * @param  Builder<Event>  $query
     */
    public function scopeVisibleToTeam(Builder $query, Team $team): void
    {
        $query->where('status', '!=', EventStatus::Draft)
            ->where(fn (Builder $query) => $query
                ->where('visibility', EventVisibility::Public)
                ->orWhereHas('invitedTeams', fn (Builder $invited) => $invited->whereKey($team->getKey())));
    }

    /**
     * @param  Builder<Event>  $query
     * @param  iterable<int>  $departmentIds
     */
    public function scopeForDepartments(Builder $query, iterable $departmentIds): void
    {
        $query->whereIn('department_id', collect($departmentIds)->all());
    }

    public function hasInvited(Team $team): bool
    {
        return $this->invitedTeams()->whereKey($team->getKey())->exists();
    }

    /**
     * Visitors may see the event page and its registration form.
     */
    public function isVisibleToVisitors(): bool
    {
        return $this->visibility === EventVisibility::Public && $this->status !== EventStatus::Draft;
    }

    public function isAcceptingApplications(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $this->status === EventStatus::Open
            && ($this->registration_opens_at === null || $this->registration_opens_at->lessThanOrEqualTo($now))
            && $now->lessThanOrEqualTo($this->registration_closes_at ?? $this->starts_at);
    }

    public function approvedCount(): int
    {
        return $this->registrations()->where('status', RegistrationStatus::Approved)->count();
    }

    public function hasCapacity(): bool
    {
        return $this->capacity === null || $this->approvedCount() < $this->capacity;
    }

    /**
     * The calendar day the event starts on, in the event's own timezone.
     */
    public function localStartDate(): Carbon
    {
        return Carbon::parse($this->starts_at->copy()->setTimezone($this->timezone)->toDateString());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('events')
            ->logOnly(['title_en', 'title_ar', 'starts_at', 'ends_at', 'registration_opens_at', 'registration_closes_at', 'status', 'visibility', 'capacity', 'results_published_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
