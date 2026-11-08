<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Services\FinanceAccounts;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Finance\Services\PayoutService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Brick\Money\Money;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 07 §5–7: payouts in the console, decisions on the approvals page,
 * "My earnings" for every signed-in person, and allocations between funds.
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
    $this->viewer = User::factory()->create();
    $this->approver = User::factory()->create();
    $departments->addMember($finance, $this->clerk);
    $departments->addMember($finance, $this->viewer);
    $departments->addMember($finance, $this->approver, isAdmin: true);
    app(DepartmentPermissionService::class)->syncMember($finance, $this->clerk, ['finance.view', 'finance.edit'], $this->approver);
    app(DepartmentPermissionService::class)->syncMember($finance, $this->viewer, ['finance.view'], $this->approver);
    $this->artisan('finance:ensure-periods');

    $accounts = app(FinanceAccounts::class);
    $this->general = $accounts->fund(AccountType::GeneralFund, 'LYD');
    $this->reinvestment = $accounts->fund(AccountType::ReinvestmentPool, 'LYD');
    $sponsor = $accounts->external(AccountType::ExternalSponsor, 'Libyana', 'LYD');
    $ledger = app(LedgerService::class);
    $income = $ledger->draft(['from_account_id' => $sponsor->id, 'to_account_id' => $this->general->id, 'amount' => Money::of('10000', 'LYD'), 'date' => '2027-03-15', 'description' => 'Seed'], $this->clerk);
    $ledger->submit($income, $this->clerk);
    $ledger->post($income, $this->approver);

    $this->payee = User::factory()->create(['name_en' => 'Coach Omar', 'birthdate' => '1990-01-01']);
    $this->confirmed = ['auth.password_confirmed_at' => now()->unix()];
});

function payoutForm(array $overrides = []): array
{
    return array_merge([
        'user_id' => test()->payee->id,
        'type' => 'bonus',
        'amount' => '400',
        'currency' => 'LYD',
        'source_account_id' => test()->general->id,
        'reason' => 'Robot Cup 2027, first place',
    ], $overrides);
}

it('lists payouts and lets editors draft and submit them', function () {
    $this->actingAs($this->viewer)->post(route('console.finance.payouts.store'), payoutForm())->assertForbidden();

    $this->actingAs($this->clerk)->post(route('console.finance.payouts.store'), payoutForm())->assertRedirect();
    $payout = Payout::query()->sole();
    $this->actingAs($this->clerk)->post(route('console.finance.payouts.submit', $payout))->assertRedirect();

    $this->actingAs($this->viewer)
        ->get(route('console.finance.payouts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/finance/Payouts')
            ->has('payouts.data', 1)
            ->where('payouts.data.0.payee', 'Coach Omar')
            ->where('payouts.data.0.status', 'submitted')
            ->where('payouts.data.0.amount.formatted', '400.000 LYD'));
});

it('validates payouts, including guardians for minors', function () {
    $minor = User::factory()->create(['birthdate' => '2013-01-01']);

    $this->actingAs($this->clerk)->post(route('console.finance.payouts.store'), payoutForm(['user_id' => $minor->id]))->assertSessionHasErrors('guardian_name');
    $this->actingAs($this->clerk)->post(route('console.finance.payouts.store'), payoutForm(['reason' => '']))->assertSessionHasErrors('reason');
    $this->actingAs($this->clerk)->post(route('console.finance.payouts.store'), payoutForm(['type' => 'gift']))->assertSessionHasErrors('type');
});

it('approves and marks payouts paid from the approvals page', function () {
    $payouts = app(PayoutService::class);
    $payout = $payouts->draft(payoutForm(), $this->clerk);
    $payouts->submit($payout, $this->clerk);

    $this->actingAs($this->approver)->withSession($this->confirmed)
        ->get(route('console.finance.approvals'))
        ->assertInertia(fn (Assert $page) => $page->has('payouts', 1)->where('payouts.0.stage', 'approve'));

    $this->actingAs($this->approver)->withSession($this->confirmed)
        ->post(route('console.finance.payouts.approve', $payout))
        ->assertRedirect();

    $this->actingAs($this->approver)->withSession($this->confirmed)
        ->post(route('console.finance.payouts.paid', $payout), ['method' => 'bank', 'reference' => '', 'paid_on' => '2027-03-15'])
        ->assertSessionHasErrors('reference');

    $this->actingAs($this->approver)->withSession($this->confirmed)
        ->post(route('console.finance.payouts.paid', $payout), ['method' => 'bank', 'reference' => 'TRF-77', 'paid_on' => '2027-03-15'])
        ->assertRedirect();

    expect($payout->fresh()->status)->toBe(PayoutStatus::Paid)
        ->and($this->general->fresh()->balance_minor)->toBe(9_600_000);
});

it('keeps payout decisions behind the password and away from impersonation', function () {
    $payouts = app(PayoutService::class);
    $payout = $payouts->draft(payoutForm(), $this->clerk);
    $payouts->submit($payout, $this->clerk);

    $this->actingAs($this->approver)->post(route('console.finance.payouts.approve', $payout))->assertRedirect(route('password.confirm'));
    $this->actingAs($this->clerk)->withSession($this->confirmed)->post(route('console.finance.payouts.approve', $payout))->assertForbidden();
});

it('rejects with a reason and cancels', function () {
    $payouts = app(PayoutService::class);
    $submitted = $payouts->draft(payoutForm(), $this->clerk);
    $payouts->submit($submitted, $this->clerk);
    $draft = $payouts->draft(payoutForm(), $this->clerk);

    $this->actingAs($this->approver)->withSession($this->confirmed)
        ->post(route('console.finance.payouts.reject', $submitted), ['reason' => 'Not in the rules'])
        ->assertRedirect();
    $this->actingAs($this->clerk)->post(route('console.finance.payouts.cancel', $draft))->assertRedirect();

    expect($submitted->fresh()->status)->toBe(PayoutStatus::Rejected)
        ->and($draft->fresh()->status)->toBe(PayoutStatus::Cancelled);
});

it('shows every person only their own payouts in My earnings', function () {
    $payouts = app(PayoutService::class);
    $mine = $payouts->draft(payoutForm(), $this->clerk);
    $payouts->submit($mine, $this->clerk);
    $payouts->approve($mine->fresh(), $this->approver);
    $payouts->markPaid($mine->fresh(), ['method' => 'cash', 'reference' => 'C-1', 'paid_on' => '2027-03-10'], $this->approver);
    $payouts->draft(payoutForm(['user_id' => $this->viewer->id]), $this->clerk);

    $this->actingAs($this->payee)
        ->get(route('account.earnings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Earnings')
            ->has('payouts', 1)
            ->where('payouts.0.reference', 'C-1')
            ->where('totals.0.amount.formatted', '400.000 LYD'));

    $this->actingAs(User::factory()->create())
        ->get(route('account.earnings'))
        ->assertInertia(fn (Assert $page) => $page->has('payouts', 0));
});

it('records allocations between funds as well as adjustments', function () {
    $this->actingAs($this->clerk)->post(route('console.finance.transactions.store'), [
        'type' => 'allocation',
        'date' => '2027-03-15',
        'from_account_id' => $this->general->id,
        'to_account_id' => $this->reinvestment->id,
        'amount' => '1000',
        'description' => 'Reinvest the Q1 surplus',
    ])->assertRedirect();

    expect(LedgerTransaction::query()->where('description', 'Reinvest the Q1 surplus')->sole()->type)->toBe(TransactionType::Allocation);

    $this->actingAs($this->clerk)->post(route('console.finance.transactions.store'), [
        'type' => 'payout',
        'date' => '2027-03-15',
        'from_account_id' => $this->general->id,
        'to_account_id' => $this->reinvestment->id,
        'amount' => '1',
        'description' => 'Sneaky',
    ])->assertSessionHasErrors('type');
});
