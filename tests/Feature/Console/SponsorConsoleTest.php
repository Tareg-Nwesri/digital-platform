<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Models\Receipt;
use App\Domain\Finance\Models\Sponsor;
use App\Domain\Finance\Models\Sponsorship;
use App\Domain\Finance\Services\FinanceAccounts;
use App\Domain\Finance\Services\ReceiptService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 07 §5–7: sponsors, sponsorships with split rules, and receipts in the
 * finance console. Editors record; approvers post receipts from the
 * password-protected approvals page.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);
    Carbon::setTestNow('2027-03-15 10:00:00');

    $superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $superAdmin->assignRole(User::SUPER_ADMIN));
    $departments = app(DepartmentService::class);
    $finance = $departments->create(['name_en' => 'Finance']);
    app(DepartmentPermissionService::class)->setCeiling($finance, ['finance.view', 'finance.edit'], $superAdmin->fresh());
    $this->clerk = User::factory()->create(['name_en' => 'Finance Clerk']);
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
    $this->lab = $accounts->create(AccountType::EarmarkedFund, 'Robotics lab', null, 'LYD');
});

function sponsorshipPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Season 2027',
        'kind' => 'cash',
        'pledged' => '10000',
        'currency' => 'LYD',
        'starts_on' => '2027-01-01',
        'ends_on' => '2027-12-31',
        'status' => 'active',
        'default_account_id' => null,
        'rules' => [
            ['account_id' => test()->lab->id, 'percent' => '25', 'fixed' => null],
        ],
    ], $overrides);
}

it('lists sponsors and lets editors add them', function () {
    Sponsor::factory()->create(['name_en' => 'Libyana Mobile']);

    $this->actingAs($this->viewer)
        ->get(route('console.finance.sponsors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('console/finance/Sponsors')->has('sponsors.data', 1));

    $this->actingAs($this->viewer)->post(route('console.finance.sponsors.store'), ['name_en' => 'X', 'type' => 'company'])->assertForbidden();

    $this->actingAs($this->clerk)->post(route('console.finance.sponsors.store'), [
        'name_en' => 'Al Madar',
        'name_ar' => 'المدار',
        'type' => 'company',
        'website' => 'https://almadar.example',
        'public_consent' => true,
    ])->assertRedirect();

    expect(Sponsor::query()->where('name_en', 'Al Madar')->sole()->public_consent)->toBeTrue();
});

it('shows a sponsor with contacts and sponsorships, and edits them', function () {
    $sponsor = Sponsor::factory()->create(['name_en' => 'Libyana Mobile']);

    $this->actingAs($this->clerk)->post(route('console.finance.sponsors.contacts.store', $sponsor), [
        'name' => 'Salma Ahmed', 'role' => 'CSR manager', 'email' => 'salma@libyana.example',
    ])->assertRedirect();
    $this->actingAs($this->clerk)->patch(route('console.finance.sponsors.update', $sponsor), [
        'name_en' => 'Libyana', 'type' => 'company', 'is_active' => false,
    ])->assertRedirect();

    $this->actingAs($this->viewer)
        ->get(route('console.finance.sponsors.show', $sponsor))
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/finance/SponsorShow')
            ->where('sponsor.name', 'Libyana')
            ->where('sponsor.isActive', false)
            ->has('contacts', 1)
            ->where('contacts.0.name', 'Salma Ahmed'));
});

it('creates a sponsorship with split rules and validates them', function () {
    $sponsor = Sponsor::factory()->create();

    $this->actingAs($this->clerk)
        ->post(route('console.finance.sponsorships.store', $sponsor), sponsorshipPayload())
        ->assertRedirect();

    $sponsorship = Sponsorship::query()->sole();
    expect($sponsorship->pledged_minor)->toBe(10_000_000)
        ->and($sponsorship->splitRules()->sole()->percent)->toBe('25.00');

    $this->actingAs($this->clerk)
        ->post(route('console.finance.sponsorships.store', $sponsor), sponsorshipPayload(['rules' => [
            ['account_id' => $this->lab->id, 'percent' => '70', 'fixed' => null],
            ['account_id' => $this->general->id, 'percent' => '40', 'fixed' => null],
        ]]))
        ->assertSessionHasErrors('rules');

    $this->actingAs($this->clerk)
        ->post(route('console.finance.sponsorships.store', $sponsor), sponsorshipPayload(['rules' => [
            ['account_id' => $this->lab->id, 'percent' => '10', 'fixed' => '100'],
        ]]))
        ->assertSessionHasErrors('rules.0.percent');

    $sponsorAccount = app(FinanceAccounts::class)->external(AccountType::ExternalSponsor, 'X', 'LYD');
    $this->actingAs($this->clerk)
        ->post(route('console.finance.sponsorships.store', $sponsor), sponsorshipPayload(['rules' => [
            ['account_id' => $sponsorAccount->id, 'percent' => '10', 'fixed' => null],
        ]]))
        ->assertSessionHasErrors('rules.0.account_id');
});

it('shows a sponsorship with pledged, received and outstanding amounts and updates its rules', function () {
    $sponsorship = Sponsorship::factory()->for(Sponsor::factory())->create(['pledged_minor' => 10_000_000]);

    $this->actingAs($this->clerk)
        ->patch(route('console.finance.sponsorships.update', $sponsorship), sponsorshipPayload([
            'title' => 'Season 2027 (renewed)',
            'rules' => [['account_id' => $this->lab->id, 'percent' => null, 'fixed' => '500']],
        ]))
        ->assertRedirect();

    $this->actingAs($this->viewer)
        ->get(route('console.finance.sponsorships.show', $sponsorship))
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/finance/SponsorshipShow')
            ->where('sponsorship.title', 'Season 2027 (renewed)')
            ->where('totals.pledged.formatted', '10,000.000 LYD')
            ->where('totals.outstanding.formatted', '10,000.000 LYD')
            ->has('sponsorship.rules', 1)
            ->where('sponsorship.rules.0.fixed', '500.000'));
});

it('records a receipt, proposes the split and submits it', function () {
    $sponsorship = Sponsorship::factory()->for(Sponsor::factory())->create();
    $sponsorship->splitRules()->create(['position' => 0, 'account_id' => $this->lab->id, 'percent' => 25]);

    $this->actingAs($this->clerk)->post(route('console.finance.receipts.store'), [
        'sponsorship_id' => $sponsorship->id,
        'source' => 'sponsorship',
        'date' => '2027-03-14',
        'amount' => '4000',
        'currency' => 'LYD',
        'method' => 'bank',
        'reference' => 'TRF-1',
    ])->assertRedirect();
    $receipt = Receipt::query()->sole();

    $this->actingAs($this->clerk)
        ->get(route('console.finance.receipts.show', $receipt))
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/finance/ReceiptShow')
            ->where('receipt.status', 'draft')
            ->has('proposal', 2)
            ->where('proposal.0.amount', '1000.000')
            ->has('accounts'));

    $this->actingAs($this->clerk)->post(route('console.finance.receipts.submit', $receipt), ['lines' => [
        ['account_id' => $this->lab->id, 'amount' => '1000'],
        ['account_id' => $this->general->id, 'amount' => '2999'],
    ]])->assertSessionHasErrors('lines');

    $this->actingAs($this->clerk)->post(route('console.finance.receipts.submit', $receipt), ['lines' => [
        ['account_id' => $this->lab->id, 'amount' => '1500'],
        ['account_id' => $this->general->id, 'amount' => '2500'],
    ]])->assertRedirect();

    expect($receipt->fresh()->status)->toBe(DocumentStatus::Submitted);
});

it('validates receipts, including in-kind items', function () {
    $this->actingAs($this->clerk)->post(route('console.finance.receipts.store'), [
        'source' => 'donation', 'date' => '2027-03-14', 'currency' => 'LYD', 'method' => 'in_kind', 'payer_name' => 'A parent',
    ])->assertSessionHasErrors('in_kind_items');

    $this->actingAs($this->clerk)->post(route('console.finance.receipts.store'), [
        'source' => 'donation', 'date' => '2027-03-14', 'currency' => 'LYD', 'method' => 'cash', 'amount' => '1.0001',
    ])->assertSessionHasErrors(['amount', 'payer_name']);
});

it('posts and rejects receipts from the approvals page only', function () {
    $sponsorship = Sponsorship::factory()->for(Sponsor::factory())->create();
    $receipts = app(ReceiptService::class);
    $receipt = $receipts->draft(['sponsorship_id' => $sponsorship->id, 'source' => 'sponsorship', 'date' => '2027-03-14', 'amount' => '300', 'currency' => 'LYD', 'method' => 'cash'], $this->clerk);
    $receipts->submit($receipt, $receipts->proposeSplit($receipt), $this->clerk);

    $this->actingAs($this->approver)->post(route('console.finance.receipts.post', $receipt))->assertRedirect(route('password.confirm'));

    $this->actingAs($this->approver)->withSession(['auth.password_confirmed_at' => now()->unix()])
        ->get(route('console.finance.approvals'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('receipts', 1)
            ->where('receipts.0.amount.formatted', '300.000 LYD')
            ->has('transactions', 0));

    $this->actingAs($this->approver)->withSession(['auth.password_confirmed_at' => now()->unix()])
        ->post(route('console.finance.receipts.post', $receipt))
        ->assertRedirect();

    expect($receipt->fresh())->status->toBe(DocumentStatus::Posted)->number->toBe('R-2027-00001')
        ->and($this->general->fresh()->balance_minor)->toBe(300_000);
});

it('lists receipts with filters', function () {
    $receipts = app(ReceiptService::class);
    $receipts->draft(['source' => 'donation', 'payer_name' => 'A parent', 'date' => '2027-03-14', 'amount' => '50', 'currency' => 'LYD', 'method' => 'cash'], $this->clerk);

    $this->actingAs($this->viewer)
        ->get(route('console.finance.receipts.index', ['filter' => ['status' => 'draft']]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/finance/Receipts')
            ->has('receipts.data', 1)
            ->where('receipts.data.0.payer', 'A parent'));
});
