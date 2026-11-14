<?php

namespace App\Domain\Work\Services;

use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Work\Models\Robot;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a robot's bill of materials (LLD 04 §4, SyncBom). Parts must be
 * visible to the team; an offer must belong to its part.
 */
final class BomService
{
    /**
     * @param  list<array{part_id: int, vendor_offer_id?: int|null, quantity: int, notes?: string|null}>  $lines
     */
    public function sync(Robot $robot, array $lines, User $by): void
    {
        $team = $robot->team;
        $partIds = array_unique(array_map(fn (array $line): int => (int) $line['part_id'], $lines));
        $visible = Part::query()->visibleTo($team)->whereKey($partIds)->pluck('id')->all();

        foreach ($lines as $index => $line) {
            if (! in_array((int) $line['part_id'], $visible, true)) {
                throw DomainRuleViolation::with(["lines.{$index}.part_id" => __('work.errors.part_not_visible')]);
            }

            $offerId = $line['vendor_offer_id'] ?? null;

            if (filled($offerId) && ! VendorOffer::query()->whereKey($offerId)->where('part_id', $line['part_id'])->exists()) {
                throw DomainRuleViolation::with(["lines.{$index}.vendor_offer_id" => __('work.errors.offer_mismatch')]);
            }
        }

        DB::transaction(function () use ($robot, $lines, $by): void {
            $robot->bomLines()->delete();

            foreach (array_values($lines) as $position => $line) {
                $robot->bomLines()->create([
                    'part_id' => (int) $line['part_id'],
                    'vendor_offer_id' => filled($line['vendor_offer_id'] ?? null) ? (int) $line['vendor_offer_id'] : null,
                    'quantity' => (int) $line['quantity'],
                    'notes' => $line['notes'] ?? null,
                    'position' => $position,
                ]);
            }

            activity('work')->performedOn($robot)->causedBy($by)
                ->withProperties(['lines' => count($lines)])
                ->event('robot.bom_synced')->log('Bill of materials saved');
        });
    }
}
