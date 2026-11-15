<?php

namespace App\Domain\Finance\Models;

use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Teams\Models\Team;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Money spent from a team, project or event budget, with its receipt
 * (LLD 07 §2). The receipt is stored on the private disk.
 *
 * @property int $id
 * @property int|null $team_id
 * @property int $budget_account_id
 * @property int|null $vendor_id
 * @property string|null $vendor_name
 * @property Carbon $date
 * @property int $amount_minor
 * @property string $currency
 * @property string $rate_to_lyd
 * @property list<array{description: string, amount_minor: int}> $items
 * @property DocumentStatus $status
 * @property string|null $rejection_reason
 * @property int|null $created_by
 * @property int|null $submitted_by
 * @property int|null $approved_by
 * @property int|null $transaction_id
 * @property-read Money $amount
 * @property-read FinanceAccount $budget
 * @property-read Vendor|null $vendor
 * @property-read Team|null $team
 */
#[Fillable([
    'team_id', 'budget_account_id', 'vendor_id', 'vendor_name', 'date', 'amount_minor', 'currency', 'rate_to_lyd',
    'items', 'status', 'rejection_reason', 'created_by', 'submitted_by', 'approved_by', 'transaction_id',
])]
class Expense extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'items' => 'array',
            'date' => 'date',
            'amount_minor' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipt')->singleFile()->useDisk('local');
    }

    public function getAmountAttribute(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    public function payee(): string
    {
        return $this->vendor?->name ?? (string) $this->vendor_name;
    }

    /**
     * @return BelongsTo<FinanceAccount, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'budget_account_id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
