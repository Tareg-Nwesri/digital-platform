<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Exceptions\ImmutableRecord;
use App\Models\User;
use App\Support\Money\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A movement of money from one account to another (LLD 07 §1). Only
 * LedgerService writes these. Posted rows never change except for the
 * one-time reversed_by_id link, and no row is ever deleted.
 *
 * @property int $id
 * @property string|null $number
 * @property Carbon $date
 * @property int|null $fiscal_period_id
 * @property int $from_account_id
 * @property int $to_account_id
 * @property int $amount_minor
 * @property string $currency
 * @property Money $amount
 * @property string $rate_to_lyd
 * @property TransactionType $type
 * @property TransactionStatus $status
 * @property string $description
 * @property string|null $reference
 * @property int|null $reverses_id
 * @property int|null $reversed_by_id
 * @property string|null $override_reason
 * @property int|null $created_by
 * @property int|null $submitted_by
 * @property int|null $approved_by
 * @property int|null $rejected_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $posted_at
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property-read FinanceAccount $from
 * @property-read FinanceAccount $to
 */
#[Fillable([
    'date', 'from_account_id', 'to_account_id', 'amount_minor', 'currency', 'rate_to_lyd', 'type', 'status',
    'description', 'reference', 'source_type', 'source_id', 'reverses_id', 'created_by',
])]
class LedgerTransaction extends Model
{
    protected static function booted(): void
    {
        static::updating(function (LedgerTransaction $transaction): void {
            if ($transaction->getOriginal('status') !== TransactionStatus::Posted) {
                return;
            }

            $changed = array_values(array_diff(array_keys($transaction->getDirty()), ['updated_at']));
            $linksReversalOnce = $changed === ['reversed_by_id'] && $transaction->getOriginal('reversed_by_id') === null;

            if ($changed !== [] && ! $linksReversalOnce) {
                throw ImmutableRecord::for('A posted ledger transaction');
            }
        });

        static::deleting(function (): void {
            throw ImmutableRecord::for('A ledger transaction');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => MoneyCast::class.':amount_minor,currency',
            'amount_minor' => 'integer',
            'rate_to_lyd' => 'decimal:8',
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'submitted_at' => 'datetime',
            'posted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FinanceAccount, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'from_account_id');
    }

    /**
     * @return BelongsTo<FinanceAccount, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'to_account_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_id !== null;
    }
}
