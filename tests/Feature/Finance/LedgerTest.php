<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\FiscalPeriod;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Services\FinanceAccounts;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Exceptions\DomainRuleViolation;
use App\Exceptions\ImmutableRecord;
use App\Models\User;
use Brick\Money\Money;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/*
 * LLD 07 §1–3: the ledger. LedgerService is the only writer. Posted rows
 * never change; mistakes are reversed. Approval is maker-checker, internal
 * accounts cannot go negative without an admin override, and closed
 * periods are locked.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);
    Carbon::setTestNow('2027-03-15 10:00:00');

    $superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $superAdmin->assignRole(User::SUPER_ADMIN));

    $departments = app(DepartmentService::class);
    $permissions = app(DepartmentPermissionService::class);
    $this->finance = $departments->create(['name_en' => 'Finance']);
    $permissions->setCeiling($this->finance, ['finance.view', 'finance.edit'], $superAdmin->fresh());

    $this->clerk = User::factory()->create(['name_en' => 'Finance Clerk']);
    $this->approver = User::factory()->create(['name_en' => 'Finance Lead']);
    $this->secondAdmin = User::factory()->create(['name_en' => 'Deputy Lead']);
    $departments->addMember($this->finance, $this->clerk);
    $departments->addMember($this->finance, $this->approver, isAdmin: true);
    $departments->addMember($this->finance, $this->secondAdmin, isAdmin: true);
    $permissions->syncMember($this->finance, $this->clerk, ['finance.view', 'finance.edit'], $this->approver);
    app(SettingsRepository::class)->set('finance.department_id', $this->finance->id);

    $this->artisan('finance:ensure-periods')->assertSuccessful();

    $this->accounts = app(FinanceAccounts::class);
    $this->ledger = app(LedgerService::class);
    $this->general = $this->accounts->fund(AccountType::GeneralFund, 'LYD');
    $this->reinvestment = $this->accounts->fund(AccountType::ReinvestmentPool, 'LYD');
    $this->sponsor = $this->accounts->external(AccountType::ExternalSponsor, 'Libyana', 'LYD');
});

function draftTransfer(FinanceAccount $from, FinanceAccount $to, string $amount, User $by, array $extra = []): LedgerTransaction
{
    return app(LedgerService::class)->draft(array_merge([
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => Money::of($amount, $from->currency),
        'date' => now()->toDateString(),
        'type' => TransactionType::Adjustment,
        'description' => 'Test movement',
    ], $extra), $by);
}

function postedTransfer(FinanceAccount $from, FinanceAccount $to, string $amount): LedgerTransaction
{
    $test = test();
    $transaction = draftTransfer($from, $to, $amount, $test->clerk);
    $test->ledger->submit($transaction, $test->clerk);
    $test->ledger->post($transaction, $test->approver);

    return $transaction->fresh();
}

it('creates each fund once per currency and external accounts lazily', function () {
    expect($this->accounts->fund(AccountType::GeneralFund, 'LYD')->is($this->general))->toBeTrue()
        ->and($this->accounts->fund(AccountType::GeneralFund, 'USD')->is($this->general))->toBeFalse()
        ->and($this->accounts->external(AccountType::ExternalSponsor, 'Libyana', 'LYD')->is($this->sponsor))->toBeTrue()
        ->and($this->general->type->isInternal())->toBeTrue()
        ->and($this->sponsor->type->isInternal())->toBeFalse();
});

it('creates the current and next month periods once', function () {
    $this->artisan('finance:ensure-periods')->assertSuccessful();

    expect(FiscalPeriod::query()->orderBy('starts_on')->get()->map(fn ($p) => $p->starts_on->toDateString())->all())
        ->toBe(['2027-03-01', '2027-04-01']);
});

it('posts a submitted transaction, numbers it and moves both balances', function () {
    $transaction = postedTransfer($this->sponsor, $this->general, '1500.250');

    expect($transaction->status)->toBe(TransactionStatus::Posted)
        ->and($transaction->number)->toBe('T-2027-000001')
        ->and($transaction->amount_minor)->toBe(1500250)
        ->and($transaction->approved_by)->toBe($this->approver->id)
        ->and($transaction->fiscal_period_id)->not->toBeNull()
        ->and($this->general->fresh()->balance_minor)->toBe(1500250)
        ->and($this->sponsor->fresh()->balance_minor)->toBe(-1500250)
        ->and((string) $this->ledger->balance($this->general->fresh())->getAmount())->toBe('1500.250')
        ->and($this->ledger->verifyAll())->toBe([])
        ->and(postedTransfer($this->sponsor, $this->general, '1')->number)->toBe('T-2027-000002')
        ->and(Activity::query()->where('event', 'finance.transaction.posted')->count())->toBe(2);
});

it('never lets the maker approve their own transaction', function () {
    $own = draftTransfer($this->sponsor, $this->general, '10', $this->approver);
    $this->ledger->submit($own, $this->approver);

    expect(fn () => $this->ledger->post($own, $this->approver))->toThrow(DomainRuleViolation::class);

    $submittedByOther = draftTransfer($this->sponsor, $this->general, '10', $this->clerk);
    $this->ledger->submit($submittedByOther, $this->secondAdmin);

    expect(fn () => $this->ledger->post($submittedByOther, $this->secondAdmin))->toThrow(DomainRuleViolation::class);

    $this->ledger->post($own, $this->secondAdmin);
    expect($own->fresh()->status)->toBe(TransactionStatus::Posted);
});

it('needs finance.approve to post', function () {
    $transaction = draftTransfer($this->sponsor, $this->general, '10', $this->approver);
    $this->ledger->submit($transaction, $this->approver);

    expect(fn () => $this->ledger->post($transaction, $this->clerk))->toThrow(DomainRuleViolation::class);
});

it('only posts submitted transactions', function () {
    $draft = draftTransfer($this->sponsor, $this->general, '10', $this->clerk);

    expect(fn () => $this->ledger->post($draft, $this->approver))->toThrow(DomainRuleViolation::class);
});

it('keeps internal accounts from going negative without an admin override', function () {
    postedTransfer($this->sponsor, $this->general, '100');

    $overdraw = draftTransfer($this->general, $this->reinvestment, '150', $this->clerk);
    $this->ledger->submit($overdraw, $this->clerk);

    expect(fn () => $this->ledger->post($overdraw, $this->approver))->toThrow(DomainRuleViolation::class);

    $this->ledger->post($overdraw, $this->approver, 'Bridging until the April grant arrives');

    expect($overdraw->fresh()->override_reason)->toBe('Bridging until the April grant arrives')
        ->and($this->general->fresh()->balance_minor)->toBe(-50000);
});

it('refuses currency mismatches and archived accounts', function () {
    $usd = $this->accounts->fund(AccountType::GeneralFund, 'USD');

    expect(fn () => draftTransfer($this->general, $usd, '10', $this->clerk))->toThrow(DomainRuleViolation::class);

    $transaction = draftTransfer($this->sponsor, $this->reinvestment, '10', $this->clerk);
    $this->ledger->submit($transaction, $this->clerk);
    $this->reinvestment->update(['is_archived' => true]);

    expect(fn () => $this->ledger->post($transaction->fresh(), $this->approver))->toThrow(DomainRuleViolation::class);
});

it('refuses transactions dated in a closed or missing period', function () {
    $period = FiscalPeriod::query()->where('starts_on', '2027-03-01')->sole();
    $this->ledger->closePeriod($period, $this->approver);

    $closed = draftTransfer($this->sponsor, $this->general, '10', $this->clerk);
    $this->ledger->submit($closed, $this->clerk);
    $missing = draftTransfer($this->sponsor, $this->general, '10', $this->clerk, ['date' => '2026-01-10']);
    $this->ledger->submit($missing, $this->clerk);

    expect(fn () => $this->ledger->post($closed, $this->approver))->toThrow(DomainRuleViolation::class)
        ->and(fn () => $this->ledger->post($missing, $this->approver))->toThrow(DomainRuleViolation::class)
        ->and($period->fresh()->closed_by)->toBe($this->approver->id);
});

it('never updates or deletes a posted transaction', function () {
    $transaction = postedTransfer($this->sponsor, $this->general, '10');

    expect(fn () => $transaction->update(['description' => 'Changed']))->toThrow(ImmutableRecord::class)
        ->and(fn () => $transaction->delete())->toThrow(ImmutableRecord::class)
        ->and(fn () => draftTransfer($this->sponsor, $this->general, '1', $this->clerk)->delete())->toThrow(ImmutableRecord::class);
});

it('reverses a posted transaction once, swapping the accounts and linking both ways', function () {
    $original = postedTransfer($this->sponsor, $this->general, '250');

    $reversal = $this->ledger->reverse($original, 'Entered twice', $this->approver);

    expect($reversal->status)->toBe(TransactionStatus::Posted)
        ->and($reversal->type)->toBe(TransactionType::Reversal)
        ->and($reversal->from_account_id)->toBe($this->general->id)
        ->and($reversal->to_account_id)->toBe($this->sponsor->id)
        ->and($reversal->reverses_id)->toBe($original->id)
        ->and($original->fresh()->reversed_by_id)->toBe($reversal->id)
        ->and($original->fresh()->isReversed())->toBeTrue()
        ->and($this->general->fresh()->balance_minor)->toBe(0)
        ->and($this->ledger->verifyAll())->toBe([]);

    expect(fn () => $this->ledger->reverse($original->fresh(), 'Again', $this->approver))->toThrow(DomainRuleViolation::class)
        ->and(fn () => $this->ledger->reverse($reversal, 'Undo the undo', $this->approver))->toThrow(DomainRuleViolation::class);
});

it('rejects a submitted transaction with a reason', function () {
    $transaction = draftTransfer($this->sponsor, $this->general, '10', $this->clerk);
    $this->ledger->submit($transaction, $this->clerk);

    $this->ledger->reject($transaction, 'No supporting document', $this->approver);

    expect($transaction->fresh())
        ->status->toBe(TransactionStatus::Rejected)
        ->rejection_reason->toBe('No supporting document')
        ->rejected_by->toBe($this->approver->id);
});

it('computes balances as of a date for statements', function () {
    postedTransfer($this->sponsor, $this->general, '100');
    Carbon::setTestNow('2027-03-20 10:00:00');
    postedTransfer($this->sponsor, $this->general, '40');

    expect((string) $this->ledger->balance($this->general, Carbon::parse('2027-03-16'))->getAmount())->toBe('100.000')
        ->and((string) $this->ledger->balance($this->general)->getAmount())->toBe('140.000');
});

it('reports cached balances that drift from the posted rows', function () {
    postedTransfer($this->sponsor, $this->general, '100');
    FinanceAccount::query()->whereKey($this->general->id)->update(['balance_minor' => 1]);

    expect($this->ledger->verifyAll())->toBe([$this->general->id => ['cached' => 1, 'computed' => 100000]]);

    $this->artisan('finance:verify')->assertFailed();
});

it('requires a positive amount and a rate for foreign currencies', function () {
    expect(fn () => draftTransfer($this->sponsor, $this->general, '0', $this->clerk))->toThrow(DomainRuleViolation::class);

    $usdSponsor = $this->accounts->external(AccountType::ExternalSponsor, 'Libyana', 'USD');
    $usdFund = $this->accounts->fund(AccountType::GeneralFund, 'USD');

    expect(fn () => draftTransfer($usdSponsor, $usdFund, '10', $this->clerk))->toThrow(DomainRuleViolation::class)
        ->and(draftTransfer($usdSponsor, $usdFund, '10', $this->clerk, ['rate_to_lyd' => '4.85'])->rate_to_lyd)->toBe('4.85000000');
});

it('seeds the shared LYD funds and open periods, safely twice', function () {
    $this->seed(FinanceSeeder::class);
    $this->seed(FinanceSeeder::class);

    expect(FinanceAccount::query()->whereIn('type', [AccountType::GeneralFund, AccountType::ReinvestmentPool])->where('currency', 'LYD')->count())->toBe(2)
        ->and(FiscalPeriod::containing(now()->toDateString()))->not->toBeNull();
});
