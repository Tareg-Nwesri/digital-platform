<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\AccountType;
use App\Support\Concerns\HasLocalizedFields;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property AccountType $type
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string $name_en
 * @property string|null $name_ar
 * @property string $currency
 * @property bool $is_restricted
 * @property string|null $purpose
 * @property int $balance_minor
 * @property bool $is_archived
 */
#[Fillable(['type', 'owner_type', 'owner_id', 'name_en', 'name_ar', 'currency', 'is_restricted', 'purpose', 'is_archived'])]
class FinanceAccount extends Model
{
    use HasLocalizedFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_restricted' => 'boolean',
            'is_archived' => 'boolean',
            'balance_minor' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<LedgerTransaction, $this>
     */
    public function outgoing(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class, 'from_account_id');
    }

    /**
     * @return HasMany<LedgerTransaction, $this>
     */
    public function incoming(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class, 'to_account_id');
    }

    /**
     * The cached balance kept by LedgerService.
     */
    public function balance(): Money
    {
        return Money::ofMinor($this->balance_minor, $this->currency);
    }

    /**
     * @param  Builder<FinanceAccount>  $query
     */
    public function scopeInternal(Builder $query): void
    {
        $query->whereIn('type', array_values(array_filter(AccountType::cases(), fn (AccountType $type): bool => $type->isInternal())));
    }
}
