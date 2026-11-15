<?php

namespace App\Domain\Finance\Services;

use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Finance\Enums\RequestStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\FundingRequest;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Work\Models\Project;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * LLD 07 §3: a team asks a fund for money. Approval (full or partial)
 * posts a Funding transaction from the fund into the project's or the
 * team's budget account, which is created on first use.
 */
final class FundingRequestService
{
    public function __construct(
        private readonly FinanceAccounts $accounts,
        private readonly LedgerService $ledger,
        private readonly TeamFeatures $features,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @param  array{amount: string, currency: string, purpose: string, breakdown?: list<array{label: string, amount: string}>|null, source_account_id: int, project_id?: int|null}  $data
     */
    public function draft(Team $team, array $data, User $by): FundingRequest
    {
        $this->assertCanRequest($team);

        return FundingRequest::query()->create([
            ...$this->attributes($team, $data),
            'team_id' => $team->getKey(),
            'status' => RequestStatus::Draft,
            'requested_by' => $by->getKey(),
        ]);
    }

    /**
     * @param  array{amount: string, currency: string, purpose: string, breakdown?: list<array{label: string, amount: string}>|null, source_account_id: int, project_id?: int|null}  $data
     */
    public function update(FundingRequest $request, array $data, User $by): void
    {
        $this->assertEditable($request);
        $request->update($this->attributes($request->team, $data));
    }

    public function submit(FundingRequest $request, User $by): void
    {
        $this->assertEditable($request);
        $this->assertCanRequest($request->team);

        $request->forceFill(['status' => RequestStatus::Submitted, 'submitted_by' => $by->getKey()])->save();
        $this->log($request, $by, 'finance.request.submitted');
    }

    public function cancel(FundingRequest $request, User $by): void
    {
        if (! $request->status->isEditable() && $request->status !== RequestStatus::Submitted) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }

        $request->forceFill(['status' => RequestStatus::Cancelled])->save();
        $this->log($request, $by, 'finance.request.cancelled');
    }

    /**
     * Approve the whole amount, or part of it with a note.
     */
    public function approve(FundingRequest $request, ?string $amount, User $by, ?string $note = null): void
    {
        $this->assertStatus($request, RequestStatus::Submitted);
        $this->assertCan($by, 'finance.approve');

        if (in_array($by->getKey(), [$request->requested_by, $request->submitted_by], true)) {
            throw DomainRuleViolation::with(['approver' => __('finance.errors.maker_checker')]);
        }

        $approved = filled($amount) ? Money::of((string) $amount, $request->currency) : $request->amount;

        if (! $approved->isPositive() || $approved->isGreaterThan($request->amount)) {
            throw DomainRuleViolation::with(['amount' => __('finance.errors.approved_amount')]);
        }

        DB::transaction(function () use ($request, $approved, $by, $note): void {
            $request->loadMissing(['team', 'project']);
            $maker = User::query()->find($request->submitted_by ?? $request->requested_by) ?? $by;
            $budget = $this->accounts->budgetFor($request->project ?? $request->team, $request->currency);

            $transaction = $this->ledger->draft([
                'from_account_id' => $request->source_account_id,
                'to_account_id' => $budget->getKey(),
                'amount' => $approved,
                'date' => now()->toDateString(),
                'type' => TransactionType::Funding,
                'description' => __('finance.funding_for', ['team' => $request->team->name, 'purpose' => $request->purpose]),
                'source' => $request,
            ], $maker);
            $this->ledger->submit($transaction, $maker);
            $this->ledger->post($transaction, $by);

            $request->forceFill([
                'status' => $approved->isEqualTo($request->amount) ? RequestStatus::Approved : RequestStatus::PartlyApproved,
                'approved_minor' => $approved->getMinorAmount()->toInt(),
                'review_note' => $note,
                'reviewed_by' => $by->getKey(),
                'reviewed_at' => now(),
                'transaction_id' => $transaction->getKey(),
            ])->save();

            $this->log($request, $by, 'finance.request.approved');
        });
    }

    public function reject(FundingRequest $request, string $reason, User $by): void
    {
        $this->review($request, RequestStatus::Rejected, $reason, $by);
    }

    public function requestChanges(FundingRequest $request, string $note, User $by): void
    {
        $this->review($request, RequestStatus::ChangesRequested, $note, $by);
    }

    /**
     * Funds a team may ask from (setting finance.request_sources).
     *
     * @return list<FinanceAccount>
     */
    public function sources(?string $currency = null): array
    {
        $types = (array) ($this->settings->get('finance.request_sources') ?? []);

        return FinanceAccount::query()
            ->whereIn('type', $types)
            ->where('is_archived', false)
            ->when($currency !== null, fn ($query) => $query->where('currency', $currency))
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function review(FundingRequest $request, RequestStatus $to, string $note, User $by): void
    {
        $this->assertStatus($request, RequestStatus::Submitted);
        $this->assertCan($by, 'finance.edit');

        $request->forceFill([
            'status' => $to,
            'review_note' => $note,
            'reviewed_by' => $by->getKey(),
            'reviewed_at' => now(),
        ])->save();

        $this->log($request, $by, 'finance.request.'.$to->value);
    }

    /**
     * @param  array{amount: string, currency: string, purpose: string, breakdown?: list<array{label: string, amount: string}>|null, source_account_id: int, project_id?: int|null}  $data
     * @return array<string, mixed>
     */
    private function attributes(Team $team, array $data): array
    {
        $currency = (string) $data['currency'];
        $amount = Money::of((string) $data['amount'], $currency);

        if (! $amount->isPositive()) {
            throw DomainRuleViolation::with(['amount' => __('finance.errors.amount_positive')]);
        }

        $projectId = $data['project_id'] ?? null;

        if ($projectId !== null && ! Project::query()->whereKey($projectId)->where('team_id', $team->getKey())->exists()) {
            throw DomainRuleViolation::with(['project_id' => __('finance.errors.not_team_project')]);
        }

        $allowed = collect($this->sources($currency))->contains(fn (FinanceAccount $source): bool => $source->getKey() === (int) $data['source_account_id']);

        if (! $allowed) {
            throw DomainRuleViolation::with(['source_account_id' => __('finance.errors.not_request_source')]);
        }

        return [
            'project_id' => $projectId,
            'amount_minor' => $amount->getMinorAmount()->toInt(),
            'currency' => $currency,
            'purpose' => $data['purpose'],
            'breakdown' => array_values(array_map(fn (array $line): array => [
                'label' => (string) $line['label'],
                'amount_minor' => Money::of((string) $line['amount'], $currency)->getMinorAmount()->toInt(),
            ], $data['breakdown'] ?? [])),
            'source_account_id' => (int) $data['source_account_id'],
        ];
    }

    private function assertCanRequest(Team $team): void
    {
        if ($team->status !== TeamStatus::Active) {
            throw DomainRuleViolation::with(['team' => __('finance.errors.budget_team_inactive')]);
        }

        $this->features->assertActive($team, TeamFeature::BudgetRequest);
    }

    private function assertEditable(FundingRequest $request): void
    {
        if (! $request->status->isEditable()) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }
    }

    private function assertStatus(FundingRequest $request, RequestStatus $status): void
    {
        if ($request->status !== $status) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }
    }

    private function assertCan(User $user, string $permission): void
    {
        if (! app(DepartmentAccess::class)->can($user, $permission)) {
            throw DomainRuleViolation::with(['approver' => __('finance.errors.not_approver')]);
        }
    }

    private function log(FundingRequest $request, User $by, string $event): void
    {
        activity('finance')->performedOn($request)->causedBy($by)
            ->withProperties(['team_id' => $request->team_id, 'amount_minor' => $request->amount_minor, 'currency' => $request->currency, 'status' => $request->status->value])
            ->event($event)
            ->log(ucfirst(str_replace(['finance.request.', '_'], ['Funding request ', ' '], $event)));
    }
}
