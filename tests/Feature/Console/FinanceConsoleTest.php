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
use App\Models\User;
use Brick\Money\Money;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 07 §4–7: the finance console. finance.view sees balances and
 * statements, finance.edit prepares transactions and accounts, and
 * finance.approve (finance department admins) approves, rejects, reverses
 * and closes periods after confirming their password, never while acting
 * as someone else.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);
    Carbon::setTestNow('2027-03-15 10:00:00');

    $this->superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));
    $this->superAdmin = $this->superAdmin->fresh();

    $departments = app(DepartmentService::class);
    $permissions = app(DepartmentPermissionService::class);
    $this->financeDepartment = $departments->create(['name_en' => 'Finance']);
    $permissions->setCeiling($this->financeDepartment, ['finance.view', 'finance.edit'], $this->superAdmin);

    $this->clerk = User::factory()->create(['name_en' => 'Finance Clerk']);
    $this->viewer = User::factory()->create(['name_en' => 'Finance Viewer']);
    $this->approver = User::factory()->create(['name_en' => 'Finance Lead']);
    $departments->addMember($this->financeDepartment, $this->clerk);
    $departments->addMember($this->financeDepartment, $this->viewer);
    $departments->addMember($this->financeDepartment, $this->approver, isAdmin: true);
    $permissions->syncMember($this->financeDepartment, $this->clerk, ['finance.view', 'finance.edit'], $this->approver);
    $permissions->syncMember($this->financeDepartment, $this->viewer, ['finance.view'], $this->approver);
    app(SettingsRepository::class)->set('finance.department_id', $this->financeDepartment->id);

    $this->artisan('finance:ensure-periods');
    $accounts = app(FinanceAccounts::class);
    $this->general = $accounts->fund(AccountType::GeneralFund, 'LYD');
    $this->reinvestment = $accounts->fund(AccountType::ReinvestmentPool, 'LYD');
    $this->sponsor = $accounts->external(AccountType::ExternalSponsor, 'Libyana', 'LYD');
});

function confirmed(): array
{
    return ['auth.password_confirmed_at' => now()->unix()];
}

function seedIncome(string $amount = '1000'): LedgerTransaction
{
    $test = test();
    $ledger = app(LedgerService::class);
    $transaction = $ledger->draft([
        'from_account_id' => $test->sponsor->id,
        'to_account_id' => $test->general->id,
        'amount' => Money::of($amount, 'LYD'),
        'date' => now()->toDateString(),
        'type' => TransactionType::Income,
        'description' => 'Sponsorship instalment',
    ], $test->clerk);
    $ledger->submit($transaction, $test->clerk);
    $ledger->post($transaction, $test->approver);

    return $transaction->fresh();
}

function transactionPayload(array $overrides = []): array
{
    return array_merge([
        'date' => '2027-03-15',
        'from_account_id' => test()->general->id,
        'to_account_id' => test()->reinvestment->id,
        'amount' => '250.500',
        'description' => 'Move surplus to reinvestment',
        'reference' => 'BOARD-2027-04',
    ], $overrides);
}

it('shows the dashboard to finance viewers only', function () {
    seedIncome();

    $this->actingAs($this->viewer)
        ->get(route('console.finance.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/finance/Dashboard')
            ->where('internal', fn ($rows) => collect($rows)->contains(fn ($row) => $row['name'] === 'General fund' && $row['balance']['formatted'] === '1,000.000 LYD'))
            ->where('pending.transactions', 0)
            ->where('can.edit', false)
            ->where('can.approve', false));

    $this->actingAs(User::factory()->create())->get(route('console.finance.dashboard'))->assertForbidden();
});

it('lists accounts and lets editors open earmarked funds and event budgets', function () {
    $this->actingAs($this->viewer)
        ->get(route('console.finance.accounts.index'))
        ->assertInertia(fn (Assert $page) => $page->component('console/finance/Accounts')->has('accounts', 3));

    $this->actingAs($this->viewer)->post(route('console.finance.accounts.store'), [
        'type' => 'earmarked_fund', 'name_en' => 'Robotics lab', 'currency' => 'LYD',
    ])->assertForbidden();

    $this->actingAs($this->clerk)->post(route('console.finance.accounts.store'), [
        'type' => 'earmarked_fund',
        'name_en' => 'Robotics lab',
        'name_ar' => 'مختبر الروبوتات',
        'currency' => 'LYD',
        'purpose' => 'Equipment for the new lab only',
    ])->assertRedirect();

    expect(FinanceAccount::query()->where('name_en', 'Robotics lab')->sole())
        ->type->toBe(AccountType::EarmarkedFund)
        ->is_restricted->toBeTrue();

    $this->actingAs($this->clerk)->post(route('console.finance.accounts.store'), [
        'type' => 'external_sponsor', 'name_en' => 'Sneaky', 'currency' => 'LYD',
    ])->assertSessionHasErrors('type');
});

it('shows an account statement with opening, running and closing balances', function () {
    Carbon::setTestNow('2027-03-02 10:00:00');
    seedIncome('100');
    Carbon::setTestNow('2027-03-10 10:00:00');
    seedIncome('50');
    Carbon::setTestNow('2027-03-15 10:00:00');

    $this->actingAs($this->viewer)
        ->get(route('console.finance.accounts.show', [$this->general, 'from' => '2027-03-05', 'to' => '2027-03-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/finance/AccountShow')
            ->where('account.name', 'General fund')
            ->where('statement.opening.formatted', '100.000 LYD')
            ->has('statement.rows', 1)
            ->where('statement.rows.0.direction', 'in')
            ->where('statement.rows.0.running.formatted', '150.000 LYD')
            ->where('statement.closing.formatted', '150.000 LYD'));
});

it('lets editors prepare and submit transactions, with validation', function () {
    seedIncome();

    $this->actingAs($this->viewer)->post(route('console.finance.transactions.store'), transactionPayload())->assertForbidden();

    $this->actingAs($this->clerk)->post(route('console.finance.transactions.store'), transactionPayload())->assertRedirect();
    $draft = LedgerTransaction::query()->where('description', 'Move surplus to reinvestment')->sole();

    expect($draft->status)->toBe(TransactionStatus::Draft)
        ->and($draft->amount_minor)->toBe(250500)
        ->and($draft->type)->toBe(TransactionType::Adjustment);

    $this->actingAs($this->clerk)->post(route('console.finance.transactions.submit', $draft))->assertRedirect();
    expect($draft->fresh()->status)->toBe(TransactionStatus::Submitted);
});

it('validates transactions', function (array $payload, string $error) {
    $this->actingAs($this->clerk)->post(route('console.finance.transactions.store'), transactionPayload($payload))->assertSessionHasErrors($error);
})->with([
    'same account' => [fn () => ['to_account_id' => $this->general->id], 'to_account_id'],
    'too many decimals' => [['amount' => '1.2345'], 'amount'],
    'zero' => [['amount' => '0'], 'amount'],
    'missing description' => [['description' => ''], 'description'],
]);

it('asks for the password before showing approvals', function () {
    $this->actingAs($this->approver)->get(route('console.finance.approvals'))->assertRedirect(route('password.confirm'));

    $this->actingAs($this->approver)->withSession(confirmed())
        ->get(route('console.finance.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('console/finance/Approvals'));

    $this->actingAs($this->viewer)->withSession(confirmed())->get(route('console.finance.approvals'))->assertForbidden();
});

it('approves with maker-checker and shows the reason when refused', function () {
    seedIncome();
    $this->actingAs($this->clerk)->post(route('console.finance.transactions.store'), transactionPayload());
    $transaction = LedgerTransaction::query()->where('description', 'Move surplus to reinvestment')->sole();
    app(LedgerService::class)->submit($transaction, $this->clerk);

    $this->actingAs($this->approver)->post(route('console.finance.transactions.approve', $transaction))->assertRedirect(route('password.confirm'));

    $this->actingAs($this->approver)->withSession(confirmed())
        ->post(route('console.finance.transactions.approve', $transaction))
        ->assertRedirect();

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Posted)
        ->and($this->reinvestment->fresh()->balance_minor)->toBe(250500);

    $own = app(LedgerService::class)->draft([
        'from_account_id' => $this->general->id, 'to_account_id' => $this->reinvestment->id,
        'amount' => Money::of('1', 'LYD'), 'date' => '2027-03-15', 'description' => 'Own',
    ], $this->approver);
    app(LedgerService::class)->submit($own, $this->approver);

    $this->actingAs($this->approver)->withSession(confirmed())
        ->from(route('console.finance.approvals'))
        ->post(route('console.finance.transactions.approve', $own))
        ->assertSessionHasErrors('approver');
});

it('rejects with a reason and reverses posted transactions', function () {
    $income = seedIncome();
    $this->actingAs($this->clerk)->post(route('console.finance.transactions.store'), transactionPayload());
    $pending = LedgerTransaction::query()->where('description', 'Move surplus to reinvestment')->sole();
    app(LedgerService::class)->submit($pending, $this->clerk);

    $this->actingAs($this->approver)->withSession(confirmed())
        ->post(route('console.finance.transactions.reject', $pending), ['reason' => ''])
        ->assertSessionHasErrors('reason');
    $this->actingAs($this->approver)->withSession(confirmed())
        ->post(route('console.finance.transactions.reject', $pending), ['reason' => 'No board minute attached'])
        ->assertRedirect();

    $this->actingAs($this->approver)->withSession(confirmed())
        ->post(route('console.finance.transactions.reverse', $income), ['reason' => 'Cheque bounced'])
        ->assertRedirect();

    expect($pending->fresh()->status)->toBe(TransactionStatus::Rejected)
        ->and($income->fresh()->isReversed())->toBeTrue()
        ->and($this->general->fresh()->balance_minor)->toBe(0);
});

it('blocks approvals while impersonating', function () {
    $income = seedIncome();

    $this->actingAs($this->superAdmin)->post(route('impersonate.take', $this->approver));

    $this->withSession(confirmed())
        ->post(route('console.finance.transactions.reverse', $income), ['reason' => 'Test'])
        ->assertForbidden();
});

it('lists transactions with filters', function () {
    seedIncome();
    $this->actingAs($this->clerk)->post(route('console.finance.transactions.store'), transactionPayload());

    $this->actingAs($this->viewer)
        ->get(route('console.finance.transactions.index', ['filter' => ['status' => 'posted']]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/finance/Transactions')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.number', 'T-2027-000001')
            ->where('transactions.data.0.amount.formatted', '1,000.000 LYD'));
});

it('closes periods for approvers', function () {
    $period = FiscalPeriod::query()->where('starts_on', '2027-03-01')->sole();

    $this->actingAs($this->viewer)
        ->get(route('console.finance.periods.index'))
        ->assertInertia(fn (Assert $page) => $page->component('console/finance/Periods')->has('periods', 2));

    $this->actingAs($this->clerk)->withSession(confirmed())->post(route('console.finance.periods.close', $period))->assertForbidden();
    $this->actingAs($this->approver)->withSession(confirmed())->post(route('console.finance.periods.close', $period))->assertRedirect();

    expect($period->fresh()->isClosed())->toBeTrue();
});
