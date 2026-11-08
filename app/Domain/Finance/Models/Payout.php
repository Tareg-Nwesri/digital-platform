<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\PaymentMethod;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Enums\PayoutType;
use App\Models\User;
use App\Support\Money\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money paid to a person (LLD 07 §1). It reaches the ledger only when
 * marked paid.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $guardian_name
 * @property string|null $guardian_contact
 * @property PayoutType $type
 * @property int $amount_minor
 * @property string $currency
 * @property Money $amount
 * @property string $rate_to_lyd
 * @property int $source_account_id
 * @property string $reason
 * @property PayoutStatus $status
 * @property PaymentMethod|null $method
 * @property string|null $reference
 * @property Carbon|null $paid_on
 * @property bool $needs_second_approval
 * @property string|null $rejection_reason
 * @property int|null $created_by
 * @property int|null $submitted_by
 * @property int|null $approved_by
 * @property int|null $second_approved_by
 * @property int|null $paid_by
 * @property int|null $transaction_id
 * @property-read User $payee
 * @property-read FinanceAccount $source
 */
#[Fillable([
    'user_id', 'guardian_name', 'guardian_contact', 'type', 'amount_minor', 'currency', 'rate_to_lyd',
    'source_account_id', 'reason', 'link_type', 'link_id', 'status', 'needs_second_approval', 'created_by',
])]
class Payout extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PayoutType::class,
            'status' => PayoutStatus::class,
            'method' => PaymentMethod::class,
            'amount' => MoneyCast::class.':amount_minor,currency',
            'amount_minor' => 'integer',
            'rate_to_lyd' => 'decimal:8',
            'paid_on' => 'date',
            'needs_second_approval' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
