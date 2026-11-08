<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Services\FinanceAccounts;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Finance\Services\PayoutService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Brick\Money\Money;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Carbon;

/*
 * LLD 07 §3, §6: payouts to people. Maker-checker approval, a second and
 * different approver from 5,000 LYD, guardian details for under-18 payees,
 * and money leaves the ledger only when the payout is marked paid.
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
    $this->secondApprover = User::factory()->create();
    $departments->addMember($finance, $this->clerk);
    $departments->addMember($finance, $this->approver, isAdmin: true);
    $departments->addMember($finance, $this->secondApprover, isAdmin: true);
    app(DepartmentPermissionService::class)->syncMember($finance, $this->clerk, ['finance.view', 'finance.edit'], $this->approver);
    $this->artisan('finance:ensure-periods');

    $accounts = app(FinanceAccounts::class);
    $this->general = $accounts->fund(AccountType::GeneralFund, 'LYD');
    $sponsor = $accounts->external(AccountType::ExternalSponsor, 'Libyana', 'LYD');
    $ledger = app(LedgerService::class);
    $income = $ledger->draft(['from_account_id' => $sponsor->id, 'to_account_id' => $this->general->id, 'amount' => Money::of('20000', 'LYD'), 'date' => '2027-03-15', 'description' => 'Seed'], $this->clerk);
    $ledger->submit($income, $this->clerk);
    $ledger->post($income, $this->approver);

    $this->adult = User::factory()->create(['name_en' => 'Coach Omar', 'birthdate' => '1990-01-01']);
    $this->minor = User::factory()->create(['name_en' => 'Young Ali', 'birthdate' => '2012-06-01']);
    $this->payouts = app(PayoutService::class);
});

function payoutData(User $payee, string $amount = '300', array $extra = []): array
{
    return array_merge([
        'user_id' => $payee->id,
        'type' => 'bonus',
        'amount' => $amount,
        'currency' => 'LYD',
        'source_account_id' => test()->general->id,
        'reason' => 'Robot Cup 2027, first place',
    ], $extra);
}

it('drafts and submits a payout, flagging large ones for a second approval', function () {
    $small = $this->payouts->draft(payoutData($this->adult), $this->clerk);
    $large = $this->payouts->draft(payoutData($this->adult, '5000'), $this->clerk);

    expect($small->status)->toBe(PayoutStatus::Draft)
        ->and($small->amount_minor)->toBe(300_000)
        ->and($small->needs_second_approval)->toBeFalse()
        ->and($large->needs_second_approval)->toBeTrue();

    $this->payouts->submit($small, $this->clerk);
    expect($small->fresh()->status)->toBe(PayoutStatus::Submitted);
});

it('requires guardian details for payees under 18', function () {
    expect(fn () => $this->payouts->draft(payoutData($this->minor), $this->clerk))->toThrow(DomainRuleViolation::class);

    $payout = $this->payouts->draft(payoutData($this->minor, '100', ['guardian_name' => 'Fatima Ali', 'guardian_contact' => '+218 91 000 0000']), $this->clerk);

    expect($payout->guardian_name)->toBe('Fatima Ali');
});

it('needs a different approver than the maker, and a second different approver for large payouts', function () {
    $large = $this->payouts->draft(payoutData($this->adult, '6000'), $this->clerk);
    $this->payouts->submit($large, $this->clerk);

    expect(fn () => $this->payouts->approve($large->fresh(), $this->clerk))->toThrow(DomainRuleViolation::class);

    $this->payouts->approve($large->fresh(), $this->approver);
    expect($large->fresh()->status)->toBe(PayoutStatus::Submitted)
        ->and($large->fresh()->approved_by)->toBe($this->approver->id);

    expect(fn () => $this->payouts->approve($large->fresh(), $this->approver))->toThrow(DomainRuleViolation::class);

    $this->payouts->approve($large->fresh(), $this->secondApprover);
    expect($large->fresh())
        ->status->toBe(PayoutStatus::Approved)
        ->second_approved_by->toBe($this->secondApprover->id);
});

it('moves money only when marked paid with a reference', function () {
    $payout = $this->payouts->draft(payoutData($this->adult), $this->clerk);
    $this->payouts->submit($payout, $this->clerk);
    $this->payouts->approve($payout->fresh(), $this->approver);

    expect($payout->fresh()->status)->toBe(PayoutStatus::Approved)
        ->and(LedgerTransaction::query()->where('type', TransactionType::Payout)->exists())->toBeFalse();

    expect(fn () => $this->payouts->markPaid($payout->fresh(), ['method' => 'bank', 'reference' => '', 'paid_on' => '2027-03-15'], $this->approver))
        ->toThrow(DomainRuleViolation::class);

    $this->payouts->markPaid($payout->fresh(), ['method' => 'bank', 'reference' => 'TRF-501', 'paid_on' => '2027-03-15'], $this->approver);

    $transaction = LedgerTransaction::query()->where('type', TransactionType::Payout)->sole();

    expect($payout->fresh())
        ->status->toBe(PayoutStatus::Paid)
        ->reference->toBe('TRF-501')
        ->paid_by->toBe($this->approver->id)
        ->and($transaction->status)->toBe(TransactionStatus::Posted)
        ->and($transaction->to->type)->toBe(AccountType::ExternalPerson)
        ->and($transaction->to->owner->is($this->adult))->toBeTrue()
        ->and($this->general->fresh()->balance_minor)->toBe(19_700_000);
});

it('refuses to pay from a fund without enough money', function () {
    $payout = $this->payouts->draft(payoutData($this->adult, '25000'), $this->clerk);
    $this->payouts->submit($payout, $this->clerk);
    $this->payouts->approve($payout->fresh(), $this->approver);
    $this->payouts->approve($payout->fresh(), $this->secondApprover);

    expect(fn () => $this->payouts->markPaid($payout->fresh(), ['method' => 'bank', 'reference' => 'TRF-9', 'paid_on' => '2027-03-15'], $this->approver))
        ->toThrow(DomainRuleViolation::class)
        ->and($payout->fresh()->status)->toBe(PayoutStatus::Approved);
});

it('rejects and cancels payouts', function () {
    $rejected = $this->payouts->draft(payoutData($this->adult), $this->clerk);
    $this->payouts->submit($rejected, $this->clerk);
    $this->payouts->reject($rejected->fresh(), 'Not in the bonus rules', $this->approver);

    $cancelled = $this->payouts->draft(payoutData($this->adult), $this->clerk);
    $this->payouts->cancel($cancelled, $this->clerk);

    expect($rejected->fresh())->status->toBe(PayoutStatus::Rejected)->rejection_reason->toBe('Not in the bonus rules')
        ->and($cancelled->fresh()->status)->toBe(PayoutStatus::Cancelled);
});

it('totals each person’s paid earnings by year', function () {
    foreach (['200', '300'] as $amount) {
        $payout = $this->payouts->draft(payoutData($this->adult, $amount), $this->clerk);
        $this->payouts->submit($payout, $this->clerk);
        $this->payouts->approve($payout->fresh(), $this->approver);
        $this->payouts->markPaid($payout->fresh(), ['method' => 'cash', 'reference' => 'C-'.$amount, 'paid_on' => '2027-03-15'], $this->approver);
    }
    $this->payouts->draft(payoutData($this->minor, '50', ['guardian_name' => 'G', 'guardian_contact' => '1']), $this->clerk);

    expect($this->payouts->totalsByYear($this->adult))->toBe([['year' => 2027, 'currency' => 'LYD', 'amountMinor' => 500_000]])
        ->and(Payout::query()->where('user_id', $this->adult->id)->count())->toBe(2);
});
