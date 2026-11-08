<?php

namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One calendar month. Closed periods accept no new postings (FR-FIN-10).
 *
 * @property int $id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 */
#[Fillable(['starts_on', 'ends_on'])]
class FiscalPeriod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public static function containing(string $date): ?self
    {
        return self::query()->where('starts_on', '<=', $date)->where('ends_on', '>=', $date)->first();
    }
}
