<?php

namespace App\Console\Commands;

use App\Domain\Finance\Models\FiscalPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('finance:ensure-periods')]
#[Description('Create the current and next month fiscal periods if they are missing')]
class EnsureFiscalPeriods extends Command
{
    public function handle(): int
    {
        foreach ([now()->startOfMonth(), now()->startOfMonth()->addMonthNoOverflow()] as $start) {
            FiscalPeriod::query()->firstOrCreate(
                ['starts_on' => $start->toDateString()],
                ['ends_on' => $start->copy()->endOfMonth()->toDateString()],
            );
        }

        return self::SUCCESS;
    }
}
