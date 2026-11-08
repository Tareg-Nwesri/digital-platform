<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Organisation\Models\Department;
use App\Models\User;
use App\Support\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property string|null $excerpt_en
 * @property string|null $excerpt_ar
 * @property string|null $body_en
 * @property string|null $body_ar
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property int|null $author_id
 * @property int|null $approved_by
 * @property-read Department $department
 */
#[Fillable([
    'department_id', 'slug', 'title_en', 'title_ar', 'excerpt_en', 'excerpt_ar', 'body_en', 'body_ar',
    'status', 'published_at', 'author_id', 'approved_by',
])]
class Post extends Model
{
    use HasLocalizedFields, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Published and due (the public site, phase 4).
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('status', PostStatus::Published)->where('published_at', '<=', now());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly(['title_en', 'title_ar', 'status', 'published_at', 'department_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
