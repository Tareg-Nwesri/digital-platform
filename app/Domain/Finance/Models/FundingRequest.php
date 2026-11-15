<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\RequestStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Work\Models\Project;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A team asks a fund for money for itself or one of its projects (LLD 07 §2).
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $project_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $purpose
 * @property list<array{label: string, amount_minor: int}>|null $breakdown
 * @property int $source_account_id
 * @property RequestStatus $status
 * @property int|null $approved_minor
 * @property string|null $review_note
 * @property int|null $requested_by
 * @property int|null $submitted_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int|null $transaction_id
 * @property-read Money $amount
 * @property-read Team $team
 * @property-read Project|null $project
 * @property-read FinanceAccount $source
 */
#[Fillable([
    'team_id', 'project_id', 'amount_minor', 'currency', 'purpose', 'breakdown', 'source_account_id', 'status',
    'approved_minor', 'review_note', 'requested_by', 'submitted_by', 'reviewed_by', 'reviewed_at', 'transaction_id',
])]
class FundingRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'breakdown' => 'array',
            'amount_minor' => 'integer',
            'approved_minor' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getAmountAttribute(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    public function approvedAmount(): ?Money
    {
        return $this->approved_minor === null ? null : Money::ofMinor($this->approved_minor, $this->currency);
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
     * @return BelongsTo<FinanceAccount, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'source_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
