<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\IncomeSource;
use App\Domain\Finance\Enums\PaymentMethod;
use App\Models\User;
use App\Support\Money\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Money coming in (LLD 07 §1). Its ledger transactions carry it as source.
 *
 * @property int $id
 * @property string|null $number
 * @property int|null $sponsorship_id
 * @property IncomeSource $source
 * @property int $payer_account_id
 * @property Carbon $date
 * @property int $amount_minor
 * @property string $currency
 * @property Money $amount
 * @property string $rate_to_lyd
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property list<array{description: string, quantity: int, value_minor: int, part_id?: int|null}>|null $in_kind_items
 * @property DocumentStatus $status
 * @property string|null $rejection_reason
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property-read FinanceAccount $payer
 * @property-read Sponsorship|null $sponsorship
 */
#[Fillable([
    'sponsorship_id', 'source', 'payer_account_id', 'date', 'amount_minor', 'currency', 'rate_to_lyd', 'method',
    'reference', 'in_kind_items', 'status', 'created_by',
])]
class Receipt extends Model
{
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
            'source' => IncomeSource::class,
            'method' => PaymentMethod::class,
            'status' => DocumentStatus::class,
            'in_kind_items' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FinanceAccount, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'payer_account_id');
    }

    /**
     * @return BelongsTo<Sponsorship, $this>
     */
    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphMany<LedgerTransaction, $this>
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'source')->orderBy('id');
    }
}
