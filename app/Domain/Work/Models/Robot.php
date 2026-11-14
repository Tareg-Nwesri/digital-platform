<?php

namespace App\Domain\Work\Models;

use App\Domain\Events\Models\EventRegistration;
use App\Domain\Teams\Models\Team;
use App\Domain\Work\Enums\RobotStatus;
use App\Support\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $project_id
 * @property string $name
 * @property string $slug
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property RobotStatus $status
 * @property int|null $weight_g
 * @property int|null $length_mm
 * @property int|null $width_mm
 * @property int|null $height_mm
 * @property bool $is_public
 * @property-read Team $team
 * @property-read Project|null $project
 */
#[Fillable([
    'team_id', 'project_id', 'name', 'slug', 'description_en', 'description_ar', 'status',
    'weight_g', 'length_mm', 'width_mm', 'height_mm', 'is_public',
])]
class Robot extends Model
{
    use HasLocalizedFields, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RobotStatus::class,
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'weight_g' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<BomLine, $this>
     */
    public function bomLines(): HasMany
    {
        return $this->hasMany(BomLine::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return BelongsToMany<EventRegistration, $this>
     */
    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(EventRegistration::class, 'registration_robot', 'robot_id', 'registration_id');
    }

    /**
     * @param  Builder<Robot>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true)->whereHas('team', fn (Builder $team) => $team->public());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('work')
            ->logOnly(['name', 'status', 'project_id', 'is_public', 'weight_g'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
