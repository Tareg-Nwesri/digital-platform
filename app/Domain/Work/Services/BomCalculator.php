<?php

namespace App\Domain\Work\Services;

use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Teams\Models\Team;
use App\Domain\Work\Enums\BomLineAvailability;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\BomLine;
use App\Domain\Work\Models\Robot;
use Brick\Money\Money;

/**
 * BOM costs (LLD 04 §4). Totals are per currency and never converted;
 * lines without a price are counted in "unpriced" and left out.
 */
final class BomCalculator
{
    /**
     * @return array{lines: list<array{line: BomLine, unit: ?Money, total: ?Money, availability: BomLineAvailability}>, totals: array<string, Money>, byVendor: list<array{vendor: Vendor, lines: list<BomLine>, total: Money}>, unpriced: int}
     */
    public function forRobot(Robot $robot): array
    {
        $lines = $robot->bomLines()->with(['part', 'offer.vendor'])->get();

        return $this->calculate($lines->all());
    }

    /**
     * Every robot that is not retired.
     *
     * @return array{totals: array<string, Money>, unpriced: int}
     */
    public function forTeam(Team $team): array
    {
        $lines = BomLine::query()
            ->whereHas('robot', fn ($query) => $query->where('team_id', $team->getKey())->where('status', '!=', RobotStatus::Retired))
            ->with(['part', 'offer.vendor'])
            ->get();

        $result = $this->calculate($lines->all());

        return ['totals' => $result['totals'], 'unpriced' => $result['unpriced']];
    }

    /**
     * @param  list<BomLine>  $lines
     * @return array{lines: list<array{line: BomLine, unit: ?Money, total: ?Money, availability: BomLineAvailability}>, totals: array<string, Money>, byVendor: list<array{vendor: Vendor, lines: list<BomLine>, total: Money}>, unpriced: int}
     */
    private function calculate(array $lines): array
    {
        $rows = [];
        $totals = [];
        $vendors = [];
        $unpriced = 0;

        foreach ($lines as $line) {
            $total = $line->lineTotal();
            $rows[] = ['line' => $line, 'unit' => $line->unitPrice(), 'total' => $total, 'availability' => $line->availability()];

            if ($total === null) {
                $unpriced++;

                continue;
            }

            $currency = $total->getCurrency()->getCurrencyCode();
            $totals[$currency] = isset($totals[$currency]) ? $totals[$currency]->plus($total) : $total;

            $vendor = $line->offer->vendor;
            $key = $vendor->getKey().'|'.$currency;
            $vendors[$key] = isset($vendors[$key])
                ? ['vendor' => $vendor, 'lines' => [...$vendors[$key]['lines'], $line], 'total' => $vendors[$key]['total']->plus($total)]
                : ['vendor' => $vendor, 'lines' => [$line], 'total' => $total];
        }

        ksort($totals);

        return ['lines' => $rows, 'totals' => $totals, 'byVendor' => array_values($vendors), 'unpriced' => $unpriced];
    }
}
