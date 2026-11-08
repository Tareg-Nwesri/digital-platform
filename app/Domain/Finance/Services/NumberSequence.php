<?php

namespace App\Domain\Finance\Services;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free yearly document numbers (LLD 07 §3): T-2027-000123 for ledger
 * transactions, R-2027-00042 for receipts. Call inside a transaction.
 */
final class NumberSequence
{
    /**
     * @var array<string, int>
     */
    private const WIDTHS = ['T' => 6, 'R' => 5];

    public function next(string $prefix, int $year): string
    {
        $query = DB::table('number_sequences')->where('prefix', $prefix)->where('year', $year);
        $row = (clone $query)->lockForUpdate()->first();

        if ($row === null) {
            DB::table('number_sequences')->insertOrIgnore(['prefix' => $prefix, 'year' => $year, 'last' => 0]);
            $row = (clone $query)->lockForUpdate()->first();
        }

        $next = (int) $row->last + 1;
        $query->update(['last' => $next]);

        return sprintf('%s-%d-%0'.(self::WIDTHS[$prefix] ?? 6).'d', $prefix, $year, $next);
    }
}
