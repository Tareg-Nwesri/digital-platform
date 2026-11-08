<?php

namespace Database\Seeders;

use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Services\FinanceAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * The shared LYD funds and the open fiscal periods (LLD 07 §1). Safe to
 * run again.
 */
class FinanceSeeder extends Seeder
{
    public function run(FinanceAccounts $accounts): void
    {
        $accounts->fund(AccountType::GeneralFund, 'LYD');
        $accounts->fund(AccountType::ReinvestmentPool, 'LYD');

        Artisan::call('finance:ensure-periods');
    }
}
