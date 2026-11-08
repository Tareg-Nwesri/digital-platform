<?php

namespace App\Console\Commands;

use App\Domain\Finance\Services\LedgerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('finance:verify')]
#[Description('Compare every cached account balance with its posted transactions')]
class VerifyLedger extends Command
{
    /**
     * Reports drift and fails; it never fixes balances on its own (LLD 07 §8).
     */
    public function handle(LedgerService $ledger): int
    {
        $mismatches = $ledger->verifyAll();

        if ($mismatches === []) {
            $this->info('All balances match their posted transactions.');

            return self::SUCCESS;
        }

        foreach ($mismatches as $accountId => ['cached' => $cached, 'computed' => $computed]) {
            $this->error("Account {$accountId}: cached {$cached}, posted rows give {$computed}.");
        }

        Log::critical('Ledger balance mismatch', ['mismatches' => $mismatches]);

        return self::FAILURE;
    }
}
