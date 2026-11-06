<?php

namespace App\Domain\Organisation\Models;

use App\Domain\Access\Models\Workspace;
use App\Domain\Teams\Models\Team;
use App\Models\User;
use App\Support\Concerns\HasLocalizedFields;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Permission;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name_en
 * @property string|null $name_ar
 * @property string $slug
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property bool $is_public
 * @property-read Workspace $workspace
 */
#[Fillable(['workspace_id', 'name_en', 'name_ar', 'slug', 'description_en', 'description_ar', 'is_public'])]
#[UseFactory(DepartmentFactory::class)]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, HasLocalizedFields, LogsActivity, SoftDeletes;

    public const ADMIN_ROLE = 'department-admin';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_members')
            ->withPivot(['is_admin', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function admins(): BelongsToMany
    {
        return $this->members()->wherePivot('is_admin', true);
    }

    /**
     * The permissions this department may use and delegate.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function ceiling(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'department_permission_ceilings');
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function supervisedTeams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * @return Collection<int, string>
     */
    public function ceilingNames(): Collection
    {
        return $this->ceiling()->pluck('name');
    }

    public function isAdmin(User $user): bool
    {
        return $this->admins()->whereKey($user->getKey())->exists();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->getKey())->exists();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('organisation')
            ->logOnly(['name_en', 'name_ar', 'slug', 'is_public'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
