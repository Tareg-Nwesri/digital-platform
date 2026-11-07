<?php

namespace App\Domain\Catalogue\Concerns;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Teams\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Shared by parts, vendors and offers (LLD 05 §3): curated by staff,
 * suggested by teams, reviewed by organisers.
 *
 * @property CatalogueStatus $status
 * @property int|null $suggested_by_team_id
 * @property int|null $suggested_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 */
trait IsCatalogueItem
{
    public function initializeIsCatalogueItem(): void
    {
        $this->mergeCasts([
            'status' => CatalogueStatus::class,
            'reviewed_at' => 'datetime',
        ]);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), CatalogueStatus::Approved);
    }

    /**
     * Approved items, plus the team's own pending suggestions.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, Team $team): void
    {
        $query->where(fn (Builder $query) => $query
            ->where($this->qualifyColumn('status'), CatalogueStatus::Approved)
            ->orWhere(fn (Builder $own) => $own
                ->where($this->qualifyColumn('status'), CatalogueStatus::Suggested)
                ->where($this->qualifyColumn('suggested_by_team_id'), $team->getKey())));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeSuggestions(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), CatalogueStatus::Suggested);
    }

    public function isSuggestionOf(Team $team): bool
    {
        return $this->status === CatalogueStatus::Suggested && $this->suggested_by_team_id === $team->getKey();
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function suggestingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'suggested_by_team_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suggester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The short type name used in routes and the review queue.
     */
    abstract public static function catalogueType(): string;
}
