<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\RequestStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Services\ExpenseService;
use App\Domain\Finance\Services\FinanceAccounts;
use App\Domain\Finance\Services\FundingRequestService;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Work\Enums\ProjectStatus;
use App\Domain\Work\Models\Project;
use App\Exceptions\DomainRuleViolation;
use App\Exceptions\FeatureUnavailable;
use App\Models\User;
use Brick\Money\Money;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/*
 * LLD 07 §3: team funding requests and expenses. A request is approved in
 * full or in part and posts a Funding transaction into the team's (or a
 * project's) budget; an approved expense posts an Expense transaction out
 * of that budget. Maker-checker and the balance check come from the ledger.
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

    $this->team = Team::factory()->create(['name' => 'Falcons', 'tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $this->captain = User::factory()->create();
    app(TeamMemberService::class)->join($this->team, $this->captain, TeamRole::Captain);
    $this->project = Project::query()->create(['team_id' => $this->team->id, 'slug' => 'line', 'title_en' => 'Line follower', 'status' => ProjectStatus::Draft]);

    $this->requests = app(FundingRequestService::class);
    $this->expenses = app(ExpenseService::class);
});

function fundingData(array $overrides = []): array
{
    return array_replace([
        'amount' => '1200',
        'currency' => 'LYD',
        'purpose' => 'Motors and sensors for the line follower.',
        'breakdown' => [['label' => 'Motors', 'amount' => '700'], ['label' => 'Sensors', 'amount' => '500']],
        'source_account_id' => test()->general->id,
        'project_id' => null,
    ], $overrides);
}

function balanceOf(FinanceAccount $account): string
{
    return (string) app(LedgerService::class)->balance($account->fresh())->getAmount();
}

it('funds the team budget in full when a request is approved', function () {
    $request = $this->requests->draft($this->team, fundingData(), $this->captain);
    expect($request->status)->toBe(RequestStatus::Draft);

    $this->requests->submit($request, $this->captain);
    $this->requests->approve($request->fresh(), null, $this->approver);

    $request->refresh();
    $budget = app(FinanceAccounts::class)->budgetFor($this->team, 'LYD');
    expect($request->status)->toBe(RequestStatus::Approved)
        ->and($request->approved_minor)->toBe(1_200_000)
        ->and($budget->type)->toBe(AccountType::TeamBudget)
        ->and(balanceOf($budget))->toBe('1200.000')
        ->and(balanceOf($this->general))->toBe('3800.000')
        ->and(LedgerTransaction::query()->where('type', TransactionType::Funding)->sole()->source_id)->toBe($request->id);
});

it('funds a project budget in part and records the note', function () {
    $request = $this->requests->draft($this->team, fundingData(['project_id' => $this->project->id]), $this->captain);
    $this->requests->submit($request, $this->captain);
    $this->requests->approve($request->fresh(), '800', $this->approver, 'Sensors from the old kit.');

    $request->refresh();
    $budget = app(FinanceAccounts::class)->budgetFor($this->project, 'LYD');
    expect($request->status)->toBe(RequestStatus::PartlyApproved)
        ->and($request->review_note)->toBe('Sensors from the old kit.')
        ->and($budget->type)->toBe(AccountType::ProjectBudget)
        ->and(balanceOf($budget))->toBe('800.000');
});

it('sends a request back for changes, then accepts the resubmission', function () {
    $request = $this->requests->draft($this->team, fundingData(), $this->captain);
    $this->requests->submit($request, $this->captain);
    $this->requests->requestChanges($request->fresh(), 'Split per robot please.', $this->approver);

    expect($request->fresh()->status)->toBe(RequestStatus::ChangesRequested);

    $this->requests->update($request->fresh(), fundingData(['amount' => '1000']), $this->captain);
    $this->requests->submit($request->fresh(), $this->captain);

    expect($request->fresh())->status->toBe(RequestStatus::Submitted)->amount_minor->toBe(1_000_000);
});

it('rejects, cancels and refuses impossible requests', function () {
    $rejected = $this->requests->draft($this->team, fundingData(), $this->captain);
    $this->requests->submit($rejected, $this->captain);
    $this->requests->reject($rejected->fresh(), 'No budget this term.', $this->approver);
    expect($rejected->fresh()->status)->toBe(RequestStatus::Rejected);

    $cancelled = $this->requests->draft($this->team, fundingData(), $this->captain);
    $this->requests->cancel($cancelled, $this->captain);
    expect($cancelled->fresh()->status)->toBe(RequestStatus::Cancelled);

    $big = $this->requests->draft($this->team, fundingData(), $this->captain);
    $this->requests->submit($big, $this->captain);
    expect(fn () => $this->requests->approve($big->fresh(), '1300', $this->approver))->toThrow(DomainRuleViolation::class);

    $other = Project::query()->create(['team_id' => Team::factory()->create()->id, 'slug' => 'x', 'title_en' => 'X', 'status' => ProjectStatus::Draft]);
    expect(fn () => $this->requests->draft($this->team, fundingData(['project_id' => $other->id]), $this->captain))->toThrow(DomainRuleViolation::class);

    $sponsorAccount = FinanceAccount::query()->where('type', AccountType::ExternalSponsor)->sole();
    expect(fn () => $this->requests->draft($this->team, fundingData(['source_account_id' => $sponsorAccount->id]), $this->captain))->toThrow(DomainRuleViolation::class);
});

it('needs the budget feature to request funding', function () {
    app(TeamFeatures::class)->override($this->team, TeamFeature::BudgetRequest, false);

    expect(fn () => $this->requests->draft($this->team, fundingData(), $this->captain))->toThrow(FeatureUnavailable::class);
});

it('spends from the budget when an expense is approved', function () {
    $request = $this->requests->draft($this->team, fundingData(), $this->captain);
    $this->requests->submit($request, $this->captain);
    $this->requests->approve($request->fresh(), null, $this->approver);
    $budget = app(FinanceAccounts::class)->budgetFor($this->team, 'LYD');

    $expense = $this->expenses->draft($this->team, [
        'budget_account_id' => $budget->id,
        'vendor_name' => 'Tripoli Electronics',
        'date' => '2027-03-14',
        'amount' => '450.500',
        'items' => [['description' => '4 x TT motors', 'amount' => '450.500']],
    ], UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'), $this->captain);

    expect($expense->getFirstMedia('receipt'))->not->toBeNull();

    $this->expenses->submit($expense, $this->captain);
    $this->expenses->approve($expense->fresh(), $this->approver);

    expect($expense->fresh()->status)->toBe(DocumentStatus::Posted)
        ->and(balanceOf($budget))->toBe('749.500')
        ->and(LedgerTransaction::query()->where('type', TransactionType::Expense)->sole()->amount_minor)->toBe(450_500);
});

it('refuses expenses beyond the budget and from other teams’ budgets', function () {
    $budget = app(FinanceAccounts::class)->budgetFor($this->team, 'LYD');
    $expense = $this->expenses->draft($this->team, [
        'budget_account_id' => $budget->id, 'vendor_name' => 'Shop', 'date' => '2027-03-14', 'amount' => '10',
        'items' => [['description' => 'Wire', 'amount' => '10']],
    ], UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'), $this->captain);
    $this->expenses->submit($expense, $this->captain);

    expect(fn () => $this->expenses->approve($expense->fresh(), $this->approver))->toThrow(DomainRuleViolation::class);

    $otherBudget = app(FinanceAccounts::class)->budgetFor(Team::factory()->create(), 'LYD');
    expect(fn () => $this->expenses->draft($this->team, [
        'budget_account_id' => $otherBudget->id, 'vendor_name' => 'Shop', 'date' => '2027-03-14', 'amount' => '10',
        'items' => [['description' => 'Wire', 'amount' => '10']],
    ], UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'), $this->captain))->toThrow(DomainRuleViolation::class);
});

it('keeps maker-checker on expenses and lets finance reject them', function () {
    $budget = app(FinanceAccounts::class)->budgetFor($this->team, 'LYD');
    $expense = $this->expenses->draft($this->team, [
        'budget_account_id' => $budget->id, 'vendor_name' => 'Shop', 'date' => '2027-03-14', 'amount' => '10',
        'items' => [['description' => 'Wire', 'amount' => '10']],
    ], UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'), $this->approver);
    $this->expenses->submit($expense, $this->approver);

    expect(fn () => $this->expenses->approve($expense->fresh(), $this->approver))->toThrow(DomainRuleViolation::class);

    $this->expenses->reject($expense->fresh(), 'Receipt is unreadable.', $this->clerk);
    expect($expense->fresh())->status->toBe(DocumentStatus::Rejected)->rejection_reason->toBe('Receipt is unreadable.');
});
