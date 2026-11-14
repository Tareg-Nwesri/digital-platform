<?php

namespace App\Domain\Teams\Models;

use App\Domain\Access\Models\Workspace;
use App\Domain\Organisation\Models\Department;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamStatus;
use App\Models\User;
use App\Support\Concerns\HasLocalizedFields;
use App\Support\Concerns\HasStatusTransitions;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Concerns\HasFeatures;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $tier_id
 * @property int|null $department_id
 * @property string $name
 * @property string $slug
 * @property TeamStatus $status
 * @property string|null $status_reason
 * @property string|null $location
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property string|null $focus_en
 * @property string|null $focus_ar
 * @property string|null $official_url
 * @property bool $is_public
 * @property Carbon|null $approved_at
 * @property-read Workspace $workspace
 * @property-read Tier $tier
 */
#[Fillable([
    'workspace_id', 'tier_id', 'department_id', 'name', 'slug', 'status', 'status_reason', 'location',
    'description_en', 'description_ar', 'focus_en', 'focus_ar', 'official_url', 'is_public',
    'approved_at', 'approved_by',
])]
#[UseFactory(TeamFactory::class)]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, HasFeatures, HasLocalizedFields, HasStatusTransitions, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TeamStatus::class,
            'is_public' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, list<TeamStatus>>
     */
    public static function allowedTransitions(): array
    {
        return [
            TeamStatus::Pending->value => [TeamStatus::Active, TeamStatus::Archived],
            TeamStatus::Active->value => [TeamStatus::Suspended, TeamStatus::Archived],
            TeamStatus::Suspended->value => [TeamStatus::Active, TeamStatus::Archived],
            TeamStatus::Archived->value => [],
        ];
    }

    /**
     * Only http(s) URLs are kept (SRS FR-WS-10, v1 FR-PTEAM-05).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function officialUrl(): Attribute
    {
        return Attribute::set(function (?string $value): ?string {
            $value = filled($value) ? trim($value) : null;

            return $value !== null && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
                ? $value
                : null;
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Tier, $this>
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    /**
     * The supervising department.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot(['joined_at', 'added_by'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<TeamFeatureOverride, $this>
     */
    public function featureOverrides(): HasMany
    {
        return $this->hasMany(TeamFeatureOverride::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Robot, $this>
     */
    public function robots(): HasMany
    {
        return $this->hasMany(Robot::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', TeamStatus::Active);
    }

    /**
     * Public teams: active, marked public and with the public.profile feature (LLD 03 §4).
     *
     * @param  Builder<static>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $feature = TeamFeature::PublicProfile->value;

        $query->where('teams.status', TeamStatus::Active)
            ->where('teams.is_public', true)
            ->whereRaw("COALESCE(
                (SELECT JSON_EXTRACT(o.value, '$') FROM team_feature_overrides o WHERE o.team_id = teams.id AND o.feature = ?),
                (SELECT JSON_EXTRACT(t.features, CONCAT('$.\"', ?, '\"')) FROM tiers t WHERE t.id = teams.tier_id)
            ) = CAST('true' AS JSON)", [$feature, $feature]);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeSupervisedBy(Builder $query, Department $department): void
    {
        $query->where('department_id', $department->getKey());
    }

    public function isActive(): bool
    {
        return $this->status === TeamStatus::Active;
    }

    public function memberCount(): int
    {
        return (int) DB::table('team_members')->where('team_id', $this->getKey())->count();
    }

    public function hasMember(User $user): bool
    {
        return DB::table('team_members')->where('team_id', $this->getKey())->where('user_id', $user->getKey())->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('teams')
            ->logOnly(['name', 'slug', 'status', 'status_reason', 'tier_id', 'department_id', 'is_public', 'location'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
