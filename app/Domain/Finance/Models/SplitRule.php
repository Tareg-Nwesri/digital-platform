<?php

namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exactly one of percent and fixed_minor is set (a database CHECK).
 *
 * @property int $id
 * @property int $sponsorship_id
 * @property int $position
 * @property int $account_id
 * @property string|null $percent
 * @property int|null $fixed_minor
 * @property-read FinanceAccount $account
 */
#[Fillable(['sponsorship_id', 'position', 'account_id', 'percent', 'fixed_minor'])]
class SplitRule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'fixed_minor' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FinanceAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }
}
