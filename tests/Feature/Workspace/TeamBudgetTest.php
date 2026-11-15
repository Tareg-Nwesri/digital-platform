<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\RequestStatus;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\FundingRequest;
use App\Domain\Finance\Services\FinanceAccounts;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Brick\Money\Money;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 07 §5 and §7: the team budget page (captain and coach) and the
 * console review of funding requests and expenses.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
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
    $sponsor = $accounts->external(AccountType::ExternalSponsor, 'Libyana', 'LYD');
    $ledger = app(LedgerService::class);
    $income = $ledger->draft(['from_account_id' => $sponsor->id, 'to_account_id' => $this->general->id, 'amount' => Money::of('5000', 'LYD'), 'date' => '2027-03-15', 'description' => 'Seed'], $this->clerk);
    $ledger->submit($income, $this->clerk);
    $ledger->post($income, $this->approver);

    $this->team = Team::factory()->create(['slug' => 'falcons', 'tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $members = app(TeamMemberService::class);
    foreach (['captain' => TeamRole::Captain, 'member' => TeamRole::Member] as $key => $role) {
        $user = User::factory()->create();
        $members->join($this->team, $user, $role);
        $this->{$key} = $user->fresh();
    }
});

function budgetPasswordConfirmed(): array
{
    return ['auth.password_confirmed_at' => now()->unix()];
}

function sendRequest(array $overrides = []): void
{
    test()->actingAs(test()->captain)->post(route('workspace.budget.requests.store', test()->team), array_replace([
        'amount' => '1000',
        'currency' => 'LYD',
        'purpose' => 'Motors and sensors.',
        'breakdown' => [['label' => 'Motors', 'amount' => '600'], ['label' => 'Sensors', 'amount' => '400']],
        'source_account_id' => test()->general->id,
    ], $overrides))->assertRedirect();
}

it('shows the budget page to captains, with funds to ask from', function () {
    $this->actingAs($this->captain)
        ->get(route('workspace.budget.index', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/budget/Index')
            ->where('budgets', [])
            ->has('sources', 1)
            ->where('can.request', true));

    $this->actingAs($this->member)->get(route('workspace.budget.index', $this->team))->assertForbidden();
});

it('sends a funding request that finance approves into the team budget', function () {
    sendRequest();
    $request = FundingRequest::query()->sole();
    expect($request->status)->toBe(RequestStatus::Submitted);

    $this->actingAs($this->approver)
        ->get(route('console.finance.budgets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/finance/TeamBudgets')
            ->where('requests.0.id', $request->id)
            ->where('requests.0.team', $this->team->name));

    $this->actingAs($this->approver)->withSession(budgetPasswordConfirmed())
        ->post(route('console.finance.requests.approve', $request), ['amount' => '800', 'note' => 'Sensors from stock.'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(RequestStatus::PartlyApproved);

    $this->actingAs($this->captain)
        ->get(route('workspace.budget.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->has('budgets', 1)
            ->where('budgets.0.balance', fn ($balance) => str_contains($balance, '800'))
            ->where('requests.0.status', 'partly_approved')
            ->where('requests.0.note', 'Sensors from stock.'));
});

it('lets the team fix a request finance sent back', function () {
    sendRequest();
    $request = FundingRequest::query()->sole();

    $this->actingAs($this->clerk)
        ->post(route('console.finance.requests.changes', $request), ['note' => 'Add the robot names.'])
        ->assertRedirect();

    $this->actingAs($this->captain)
        ->put(route('workspace.budget.requests.update', [$this->team, $request]), [
            'amount' => '900', 'currency' => 'LYD', 'purpose' => 'Motors for Falcon One.', 'breakdown' => [], 'source_account_id' => $this->general->id,
        ])
        ->assertRedirect();

    expect($request->fresh())->status->toBe(RequestStatus::Submitted)->amount_minor->toBe(900_000);

    $this->actingAs($this->captain)->post(route('workspace.budget.requests.cancel', [$this->team, $request]))->assertRedirect();
    expect($request->fresh()->status)->toBe(RequestStatus::Cancelled);
});

it('records an expense with its receipt that finance approves or rejects', function () {
    sendRequest();
    $this->actingAs($this->approver)->withSession(budgetPasswordConfirmed())
        ->post(route('console.finance.requests.approve', FundingRequest::query()->sole()))
        ->assertRedirect();
    $budget = app(FinanceAccounts::class)->budgetFor($this->team, 'LYD');

    $this->actingAs($this->captain)
        ->post(route('workspace.budget.expenses.store', $this->team), [
            'budget_account_id' => $budget->id,
            'vendor_name' => 'Tripoli Electronics',
            'date' => '2027-03-14',
            'amount' => '250',
            'items' => [['description' => 'Motors', 'amount' => '250']],
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect();

    $expense = Expense::query()->sole();
    expect($expense->status)->toBe(DocumentStatus::Submitted);

    $this->actingAs($this->approver)->get(route('console.finance.expenses.receipt', $expense))->assertOk();
    $this->actingAs($this->captain)->get(route('workspace.budget.expenses.receipt', [$this->team, $expense]))->assertOk();

    $this->actingAs($this->approver)->withSession(budgetPasswordConfirmed())
        ->post(route('console.finance.expenses.approve', $expense))
        ->assertRedirect();

    expect($expense->fresh()->status)->toBe(DocumentStatus::Posted)
        ->and((string) app(LedgerService::class)->balance($budget->fresh())->getAmount())->toBe('750.000');
});

it('validates expenses and keeps other teams out', function () {
    $this->actingAs($this->captain)
        ->post(route('workspace.budget.expenses.store', $this->team), ['amount' => '10', 'items' => []])
        ->assertSessionHasErrors(['budget_account_id', 'date', 'items', 'receipt']);

    $other = Team::factory()->create();
    $budget = app(FinanceAccounts::class)->budgetFor($other, 'LYD');
    $expense = Expense::query()->create([
        'team_id' => $other->id, 'budget_account_id' => $budget->id, 'vendor_name' => 'X', 'date' => '2027-03-01',
        'amount_minor' => 1000, 'currency' => 'LYD', 'items' => [], 'status' => DocumentStatus::Submitted,
    ]);

    $this->actingAs($this->captain)->get(route('workspace.budget.expenses.receipt', [$this->team, $expense]))->assertNotFound();
});

it('keeps console approvals behind password confirmation and finance rights', function () {
    sendRequest();
    $request = FundingRequest::query()->sole();

    $this->actingAs($this->approver)
        ->post(route('console.finance.requests.approve', $request))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($this->captain)->get(route('console.finance.budgets.index'))->assertForbidden();
    $this->actingAs($this->captain)->post(route('console.finance.requests.reject', $request), ['reason' => 'x'])->assertForbidden();
});
