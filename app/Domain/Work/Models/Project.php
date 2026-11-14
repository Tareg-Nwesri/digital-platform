<?php

namespace App\Domain\Work\Models;

use App\Domain\Teams\Models\Team;
use App\Domain\Work\Enums\ProjectStatus;
use App\Support\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $team_id
 * @property string $slug
 * @property string $title_en
 * @property string|null $title_ar
 * @property string|null $excerpt_en
 * @property string|null $excerpt_ar
 * @property string|null $body_en
 * @property string|null $body_ar
 * @property ProjectStatus $status
 * @property Carbon|null $published_at
 * @property bool $is_featured
 * @property-read Team $team
 */
#[Fillable(['team_id', 'slug', 'title_en', 'title_ar', 'excerpt_en', 'excerpt_ar', 'body_en', 'body_ar', 'status', 'published_at', 'created_by'])]
class Project extends Model
{
    use HasLocalizedFields, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
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
     * @return HasMany<Robot, $this>
     */
    public function robots(): HasMany
    {
        return $this->hasMany(Robot::class);
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProjectStatus::Published)->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->published()->whereHas('team', fn (Builder $team) => $team->public());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('work')
            ->logOnly(['title_en', 'title_ar', 'status', 'published_at'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
