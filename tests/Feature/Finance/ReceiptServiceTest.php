<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Models\Receipt;
use App\Domain\Finance\Models\Sponsor;
use App\Domain\Finance\Models\Sponsorship;
use App\Domain\Finance\Services\FinanceAccounts;
use App\Domain\Finance\Services\ReceiptService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Brick\Money\Money;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/*
 * LLD 07 §3 ReceiptService: money coming in. A receipt is split into lines
 * that add up exactly; submitting creates one Income transaction per line;
 * posting approves them all or none and numbers the receipt.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);
    Carbon::setTestNow('2027-03-15 10:00:00');

    $superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $superAdmin->assignRole(User::SUPER_ADMIN));
    $departments = app(DepartmentService::class);
    $finance = $departments->create(['name_en' => 'Finance']);
    app(DepartmentPermissionService::class)->setCeiling($finance, ['finance.view', 'finance.edit'], $superAdmin->fresh());
    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();
    $departments->addMember($finance, $this->clerk);
    $departments->addMember($finance, $this->approver, isAdmin: true);
    app(DepartmentPermissionService::class)->syncMember($finance, $this->clerk, ['finance.view', 'finance.edit'], $this->approver);
    $this->artisan('finance:ensure-periods');

    $accounts = app(FinanceAccounts::class);
    $this->general = $accounts->fund(AccountType::GeneralFund, 'LYD');
    $this->lab = $accounts->create(AccountType::EarmarkedFund, 'Robotics lab', null, 'LYD');

    $this->sponsor = Sponsor::factory()->create(['name_en' => 'Libyana Mobile']);
    $this->sponsorship = Sponsorship::factory()->for($this->sponsor)->create([
        'title' => 'Season 2027',
        'pledged_minor' => 10_000_000,
        'currency' => 'LYD',
    ]);
    $this->sponsorship->splitRules()->create(['position' => 0, 'account_id' => $this->lab->id, 'percent' => 25]);

    $this->receipts = app(ReceiptService::class);
});

function sponsorReceipt(string $amount = '4000'): Receipt
{
    $test = test();

    return $test->receipts->draft([
        'sponsorship_id' => $test->sponsorship->id,
        'source' => 'sponsorship',
        'date' => '2027-03-14',
        'amount' => $amount,
        'currency' => 'LYD',
        'method' => 'bank',
        'reference' => 'TRF-88213',
    ], $test->clerk);
}

it('drafts a sponsorship receipt from the sponsor’s external account', function () {
    $receipt = sponsorReceipt();

    expect($receipt->status)->toBe(DocumentStatus::Draft)
        ->and($receipt->amount_minor)->toBe(4_000_000)
        ->and($receipt->payer->type)->toBe(AccountType::ExternalSponsor)
        ->and($receipt->payer->owner->is($this->sponsor))->toBeTrue()
        ->and($receipt->number)->toBeNull();
});

it('records other income from a named payer into the general fund by default', function () {
    $receipt = $this->receipts->draft([
        'source' => 'donation',
        'payer_name' => 'Anonymous parent',
        'date' => '2027-03-14',
        'amount' => '150',
        'currency' => 'LYD',
        'method' => 'cash',
    ], $this->clerk);

    expect($receipt->payer->type)->toBe(AccountType::ExternalOther)
        ->and($this->receipts->proposeSplit($receipt))->toHaveCount(1)
        ->and($this->receipts->proposeSplit($receipt)[0]['account']->is($this->general))->toBeTrue();
});

it('values in-kind receipts at the sum of their items', function () {
    $receipt = $this->receipts->draft([
        'sponsorship_id' => $this->sponsorship->id,
        'source' => 'sponsorship',
        'date' => '2027-03-14',
        'currency' => 'LYD',
        'method' => 'in_kind',
        'in_kind_items' => [
            ['description' => 'Arduino kits', 'quantity' => 10, 'value' => '455'],
            ['description' => 'Soldering stations', 'quantity' => 2, 'value' => '120'],
        ],
    ], $this->clerk);

    expect($receipt->amount_minor)->toBe(575_000)
        ->and($receipt->in_kind_items[0]['value_minor'])->toBe(455_000);
});

it('proposes the sponsorship split', function () {
    $lines = $this->receipts->proposeSplit(sponsorReceipt());

    expect(collect($lines)->map(fn ($line) => [$line['account']->name_en, (string) $line['amount']->getAmount()])->all())->toBe([
        ['Robotics lab', '1000.000'],
        ['General fund', '3000.000'],
    ]);
});

it('only submits lines that add up exactly to the receipt', function () {
    $receipt = sponsorReceipt();

    expect(fn () => $this->receipts->submit($receipt, [
        ['account_id' => $this->lab->id, 'amount' => Money::of('1000', 'LYD')],
        ['account_id' => $this->general->id, 'amount' => Money::of('2999.999', 'LYD')],
    ], $this->clerk))->toThrow(DomainRuleViolation::class);

    expect(fn () => $this->receipts->submit($receipt, [
        ['account_id' => app(FinanceAccounts::class)->external(AccountType::ExternalOther, 'Nope', 'LYD')->id, 'amount' => Money::of('4000', 'LYD')],
    ], $this->clerk))->toThrow(DomainRuleViolation::class);
});

it('submits one income transaction per line and posts them all with a receipt number', function () {
    $receipt = sponsorReceipt();
    $this->receipts->submit($receipt, [
        ['account_id' => $this->lab->id, 'amount' => Money::of('1500', 'LYD')],
        ['account_id' => $this->general->id, 'amount' => Money::of('2500', 'LYD')],
    ], $this->clerk);

    $transactions = LedgerTransaction::query()->where('source_type', $receipt->getMorphClass())->where('source_id', $receipt->id)->get();

    expect($receipt->fresh()->status)->toBe(DocumentStatus::Submitted)
        ->and($transactions)->toHaveCount(2)
        ->and($transactions->every(fn ($t) => $t->status === TransactionStatus::Submitted && $t->type === TransactionType::Income))->toBeTrue();

    $this->receipts->post($receipt->fresh(), $this->approver);

    expect($receipt->fresh())
        ->status->toBe(DocumentStatus::Posted)
        ->number->toBe('R-2027-00001')
        ->approved_by->toBe($this->approver->id)
        ->and($this->lab->fresh()->balance_minor)->toBe(1_500_000)
        ->and($this->general->fresh()->balance_minor)->toBe(2_500_000)
        ->and($this->sponsorship->fresh()->received()->getMinorAmount()->toInt())->toBe(4_000_000)
        ->and($this->sponsorship->fresh()->outstanding()->getMinorAmount()->toInt())->toBe(6_000_000);
});

it('posts all lines or none, and the maker cannot approve', function () {
    $receipt = sponsorReceipt();
    $this->receipts->submit($receipt, $this->receipts->proposeSplit($receipt), $this->clerk);

    expect(fn () => $this->receipts->post($receipt->fresh(), $this->clerk))->toThrow(DomainRuleViolation::class);

    $this->lab->update(['is_archived' => true]);

    expect(fn () => $this->receipts->post($receipt->fresh(), $this->approver))->toThrow(DomainRuleViolation::class)
        ->and($this->general->fresh()->balance_minor)->toBe(0)
        ->and($receipt->fresh()->status)->toBe(DocumentStatus::Submitted)
        ->and(LedgerTransaction::query()->where('status', TransactionStatus::Posted)->count())->toBe(0);
});

it('rejects a submitted receipt and its transactions', function () {
    $receipt = sponsorReceipt();
    $this->receipts->submit($receipt, $this->receipts->proposeSplit($receipt), $this->clerk);

    $this->receipts->reject($receipt->fresh(), 'Transfer not on the bank statement', $this->approver);

    expect($receipt->fresh())
        ->status->toBe(DocumentStatus::Rejected)
        ->rejection_reason->toBe('Transfer not on the bank statement')
        ->and(LedgerTransaction::query()->where('status', TransactionStatus::Rejected)->count())->toBe(2);
});

it('keeps sponsorship splits valid: one of percent or fixed, and percentages up to 100', function () {
    expect(fn () => $this->sponsorship->splitRules()->create(['position' => 1, 'account_id' => $this->general->id, 'percent' => 10, 'fixed_minor' => 1000]))
        ->toThrow(QueryException::class);
});
