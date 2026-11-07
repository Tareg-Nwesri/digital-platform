# 07 · Finance: ledger, sponsors, budgets, expenses, payouts

SRS: FR-FIN-01…34, NFR-FIN-01…03 · HLD: 9 (D-05, D-06, D-10)

## 1. Tables

### finance_accounts
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| type | varchar(24) | no | `AccountType`, index |
| owner_type, owner_id | varchar / bigint | yes | morph: Team, Project, Event, Sponsor, Vendor, User; null for funds |
| name_en / name_ar | varchar(160) | no / yes | e.g. "General fund", "Robo Lions budget" |
| currency | char(3) | no | the account's currency; one account per currency per owner |
| is_restricted | boolean | no | earmarked funds |
| purpose | varchar(500) | yes | restriction text |
| balance_minor | bigint | no | 0; a cache, updated only by `LedgerService` |
| is_archived | boolean | no | false |
| timestamps | | | unique(type, owner_type, owner_id, currency) |

Seeded accounts: `GeneralFund` LYD and `ReinvestmentPool` LYD. External accounts are created lazily per sponsor, vendor or person, in each currency used.

### ledger_transactions
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| number | varchar(20) | yes | `T-2026-000123`, set on posting |
| date | date | no | the value date; index |
| fiscal_period_id | FK fiscal_periods | yes | set on posting |
| from_account_id | FK finance_accounts | no | restrictOnDelete; index |
| to_account_id | FK finance_accounts | no | restrictOnDelete; index |
| amount_minor | bigint unsigned | no | > 0 |
| currency | char(3) | no | must equal both accounts' currency |
| rate_to_lyd | decimal(18,8) | no | 1 for LYD |
| type | varchar(24) | no | `TransactionType` |
| status | varchar(16) | no | `TransactionStatus`, index(status, date) |
| description | varchar(500) | no | |
| reference | varchar(120) | yes | bank, cheque or invoice reference |
| source_type, source_id | morph | yes | Receipt, FundingRequest, Expense, Payout, Allocation |
| reverses_id | FK ledger_transactions | yes | unique: an entry can be reversed only once |
| reversed_by_id | FK ledger_transactions | yes | |
| override_reason | varchar(500) | yes | negative-balance override |
| created_by, submitted_by, approved_by, rejected_by | FK users | yes | |
| submitted_at, posted_at, rejected_at | timestamp | yes | |
| rejection_reason | varchar(500) | yes | |
| timestamps | | | **no soft deletes, no hard deletes** (enforced in the model) |

Media: `attachment` (multiple, private `local` disk).

### fiscal_periods
`starts_on date`, `ends_on date` (unique), `closed_at`, `closed_by`. Created automatically per month by `finance:ensure-periods`.

### sponsors, sponsor_contacts
- **sponsors:**
  - `name_en`, `name_ar`, `type` (`SponsorType`: Company, Organisation, Individual, Government), `website`, `notes`, `public_consent` boolean, `is_active`;
  - logo media;
  - soft delete is **not** used; sponsors are deactivated instead.
- **sponsor_contacts:** `sponsor_id`, `name`, `role`, `phone`, `email`.

### sponsorships
| Column | Type | Notes |
| --- | --- | --- |
| sponsor_id | FK sponsors | |
| title | varchar(160) | |
| kind | varchar(12) | `SponsorshipKind`: Cash, InKind, Mixed |
| pledged_minor, currency | bigint, char(3) | |
| starts_on, ends_on | date null | |
| purpose, restrictions | text null | |
| team_id, event_id, project_id | FK null | optional link |
| default_account_id | FK finance_accounts null | where the remainder goes; null means the general fund |
| status | varchar(16) | `SponsorshipStatus`: Draft, Active, Completed, Cancelled |
| created_by | FK users | |
| timestamps | | |

Media: `agreement` (private).

### split_rules
`sponsorship_id`, `position`, `account_id` (target), `percent decimal(5,2) null`, `fixed_minor bigint null`. A check ensures exactly one of `percent` and `fixed_minor` is set.

### receipts
| Column | Type | Notes |
| --- | --- | --- |
| number | varchar(20) | `R-2026-00042` (SRS C-15), set on posting |
| sponsorship_id | FK null | null = other income |
| source | varchar(16) | `IncomeSource`: Sponsorship, Donation, Prize, EventFee, Grant, Other |
| payer_account_id | FK finance_accounts | the external account money comes from |
| date | date | |
| amount_minor, currency, rate_to_lyd | | |
| method | varchar(12) | `PaymentMethod`: Bank, Cash, Cheque, InKind, Other |
| reference | varchar(120) null | |
| in_kind_items | json null | `[{description, quantity, value_minor, part_id?}]` |
| status | varchar(16) | `DocumentStatus`: Draft, Submitted, Posted, Rejected |
| created_by, approved_by | | |
| timestamps | | |

Media: `proof` (private).

### funding_requests
`team_id`, `project_id null`, `amount_minor`, `currency`, `purpose`, `breakdown json` (`[{label, amount_minor, robot_id?}]`), `source_account_id` (the requested fund), `status` (`RequestStatus`: Draft, Submitted, ChangesRequested, Approved, PartlyApproved, Rejected, Cancelled), `approved_minor null`, `review_note`, `requested_by`, `reviewed_by`, `reviewed_at`, timestamps.

### expenses
`budget_account_id` (team, project or event budget), `vendor_id null`, `vendor_name null`, `date`, `amount_minor`, `currency`, `rate_to_lyd`, `items json`, `bom_line_ids json null`, `status` (`DocumentStatus`), `submitted_by`, `approved_by`, `rejection_reason`, timestamps. Media: `receipt` (private).

### payouts
| Column | Type | Notes |
| --- | --- | --- |
| user_id | FK users | the payee (may be a member without login) |
| guardian_name, guardian_contact | varchar null | required when the payee is under 18 (FR-FIN-30) |
| type | varchar(16) | `PayoutType`: Bonus, Stipend, Payment, PrizeShare, Reimbursement |
| amount_minor, currency, rate_to_lyd | | |
| source_account_id | FK finance_accounts | a fund or budget |
| reason | varchar(500) | |
| link_type, link_id | morph null | EventResult, Project, Expense |
| status | varchar(16) | `PayoutStatus`: Draft, Submitted, Approved, Paid, Rejected, Cancelled |
| method | varchar(12) null | PaymentMethod |
| reference | varchar(120) null | required for Paid |
| paid_on | date null | |
| needs_second_approval | boolean | true when the LYD value is ≥ `finance.second_approval_threshold` (5,000 LYD, SRS C-14) |
| created_by, approved_by, second_approved_by, paid_by | FK users | |
| timestamps | | |

Media: `proof` (private).

### allocations
`from_account_id`, `to_account_id`, `amount_minor`, `currency`, `purpose`, `status` (`DocumentStatus`), `created_by`, `approved_by`, timestamps. Allocations are for reinvestment that has no request (FR-FIN-21).

### bonus_rules (C priority)
`event_category_id`, `placement`, `amount_minor`, `currency`, `split` (`equal` or `custom`), `is_active`.

## 2. Enums

| Enum | Cases |
| --- | --- |
| `AccountType` | GeneralFund, EarmarkedFund, ReinvestmentPool, TeamBudget, ProjectBudget, EventBudget, ExternalSponsor, ExternalVendor, ExternalPerson, ExternalOther |
| `TransactionType` | Income, Allocation, Funding, Expense, Payout, Return, Reversal, Adjustment |
| `TransactionStatus` | Draft, Submitted, Posted, Rejected (plus Reversed, derived from `reversed_by_id`) |
| `DocumentStatus` | Draft, Submitted, Posted, Rejected |

Internal account types are GeneralFund, EarmarkedFund, ReinvestmentPool and the Budget types. External types are the others; external accounts may go negative because they only mirror outside money.

## 3. The ledger core

### `LedgerService` (the only writer of `ledger_transactions` and `balance_minor`)
```php
public function draft(TransactionData $d, User $by): LedgerTransaction;
public function submit(LedgerTransaction $t, User $by): void;
public function post(LedgerTransaction $t, User $approver, ?string $overrideReason = null): void;
public function reject(LedgerTransaction $t, string $reason, User $by): void;
public function reverse(LedgerTransaction $t, string $reason, User $by): LedgerTransaction;   // creates and posts the reversal (needs approve)
public function balance(FinanceAccount $a, ?CarbonInterface $asOf = null): Money;           // computed from rows; asOf for statements
public function verifyAll(): array;                                                          // [account_id => [cached, computed]] mismatches
```

**`post()`** runs in one database transaction:
1. Refuse unless `$t->status === Submitted`.
2. **Maker-checker:** refuse when `$approver->id` equals `created_by` or `submitted_by`.
3. Check the approver has `finance.approve` and `RequireRecentTwoFactor` has passed (route middleware).
4. Lock the accounts **in id order**: `FinanceAccount::whereIn('id', [from, to])->orderBy('id')->lockForUpdate()->get()`, so two posts can never deadlock.
5. Both accounts' currencies equal the transaction currency, and neither account is archived.
6. Find the fiscal period for `$t->date`. It must exist and be open (`FR-FIN-10`).
7. If `from` is an internal account and `from.balance_minor − amount < 0`:
   - with an override reason and a department admin of the finance department: record the reason;
   - otherwise: throw `DomainRuleViolation('insufficient_balance')`.
8. Set `status=Posted`, `approved_by`, `posted_at`, `fiscal_period_id`, and `number = NumberSequence::next('T', year)`.
9. `from.balance_minor -= amount`; `to.balance_minor += amount` (an atomic `increment`/`decrement` on the locked rows).
10. Log the activity (`finance.transaction.posted`) and fire `TransactionPosted`. The source document listener updates the document's status.

**Immutability:** the `LedgerTransaction::updating` hook throws `ImmutableRecord` when the original status is Posted. The only exception is `reversed_by_id`, which is set once by `reverse()`. The `deleting` hook always throws.

**`reverse()`:**
- creates a transaction with from and to swapped, the same amount, currency and rate, `type=Reversal`, `reverses_id = t.id` and today's date (in the open period);
- posts it by the same rules, with the reverser as approver; maker-checker does not apply, because there is no maker;
- sets `t.reversed_by_id`.

### `NumberSequence`
- Table `number_sequences(prefix, year, last)`.
- `next(prefix, year)` uses `lockForUpdate` and increments `last`. It returns `sprintf('%s-%d-%06d')` for transactions, and `%05d` for receipts.

### `SplitCalculator` (pure)
```php
/** @return list<array{account: FinanceAccount, amount: Money}> */
public function propose(Money $amount, Sponsorship $s): array;
```
1. `remaining = amount`.
2. **Fixed rules**, in position order: `take = min(rule.fixed, remaining)`; add a line; `remaining -= take`. Skip when `remaining` is zero.
3. **Percentage rules:**
   - `pctTotal = Σ percent` (validated to be ≤ 100);
   - the base for all percentages is the remaining amount after the fixed rules;
   - `parts = remaining.allocate(...weights)`, where the weights are `[p1, p2, …, 100 − pctTotal]` scaled to integers (× 100);
   - each percentage line gets its part, and the last weight is the remainder.
   - brick/money `allocate` gives the leftover minor units to the first parts, so the sum always equals `remaining`.
4. **Remainder:** if greater than zero, it goes to `default_account_id`, or the GeneralFund in that currency (created if missing).
5. **Merge:** lines to the same account are merged.

### `ReceiptService`
- `draft(ReceiptData)`.
- `proposeSplit(Receipt)` runs `SplitCalculator` (or one line to the GeneralFund when there is no sponsorship).
- `submit(Receipt, array $lines)`: the edited lines must sum exactly to the receipt amount. It creates one `Income` ledger transaction per line (from the payer's external account to the target), all Submitted, with the receipt as their source.
- `post(Receipt, User)` posts every line in one database transaction (all or nothing), sets the receipt to Posted and assigns its `R-` number.
- **In-kind receipts** use `method=InKind` and the sum of `in_kind_items.value_minor`. They are optionally linked to catalogue parts. No stock is tracked in v2.

### Document services (each creates and posts ledger transactions; none edits a posted one)

| Service | Flow | Ledger effect when approved |
| --- | --- | --- |
| `FundingRequestService` | team draft → submit → finance `approve(full or partial amount)`, `reject(reason)` or `requestChanges(note)` | `Funding` transaction from `source_account` to the project's (or team's) budget account (created on demand), posted by the approver |
| `AllocationService` | finance draft → submit → approve | `Allocation` transaction from one account to another |
| `ExpenseService` | team or finance draft → submit → approve or reject | `Expense` transaction from the budget account to the vendor's external account (or ExternalOther with vendor_name) |
| `PayoutService` | finance draft → submit → approve (→ second approve when `needs_second_approval`) → `markPaid(method, reference, date, proof)` | On `markPaid`: a `Payout` transaction from the source account to the person's external account. **[Decided]** The money leaves the ledger when it is paid, not when it is approved |
| `ProjectCloseService` | `returnUnspent(Project, target: source or reinvestment)` | `Return` transactions back to the funding sources (in proportion to what each funded) or to the reinvestment pool |
| `PrizeIncomeService` | `recordPrize(Team, Money, Event)` | Income split by `finance.prize_split` (50% team budget and 50% reinvestment, SRS C-17) |

### `FundingTrace` (FR-FIN-16, FR-FIN-23)
- `sourcesFor(FinanceAccount $budget): list<{source: FinanceAccount|Sponsor, amount: Money}>` walks the incoming posted transactions. For a fund that was itself fed by sponsors, it attributes proportionally to the fund's income mix over its lifetime.
- `spendFor(Sponsorship $s): list<{account, amount}>`: where the sponsorship's money went, one hop past its split targets.

**Simplification:** attribution is proportional, not exact item-by-item tracking. Reports label it "estimated by proportion".

## 4. Policies

| Policy | Ability | Rule |
| --- | --- | --- |
| `FinancePolicy` (console) | `view` | `finance.view` |
| | `draft`, `submit` (transactions, receipts, allocations, expenses, payouts, sponsors, sponsorships) | `finance.edit` |
| | `approve`, `post`, `reject`, `reverse`, `closePeriod` | `finance.approve` and not the maker (checked again in the service) and not impersonating |
| | `override` (negative balance) | Admin of the department that holds the finance ceiling |
| | `secondApprove` | `finance.approve` and differs from the first approver |
| `TeamFinancePolicy` (workspace) | `viewBudget` | `budget.manage` (captain or coach) |
| | `requestFunding`, `submitExpense` | `budget.manage`, feature `budget.request`, and the team is active |
| `PayoutPolicy` | `viewOwn` | Any authenticated user, for their own payouts only (the "My earnings" route has no id parameter) |

## 5. Routes

**Console** (`/admin/finance`, middleware auth; approve routes also `not-impersonating`, `password.confirm`):

| Method | URI | Name |
| --- | --- | --- |
| GET | `/finance` | `console.finance.dashboard` |
| GET | `/finance/accounts` · `/finance/accounts/{account}` | `.accounts.index` · `.accounts.show` (statement with a date range) |
| POST | `/finance/accounts` | `.accounts.store` (earmarked funds, event budgets) |
| GET/POST | `/finance/transactions` | `.transactions.*` (a manual Adjustment draft needs a reason) |
| POST | `/finance/transactions/{t}/submit` · `/approve` · `/reject` · `/reverse` | `.transactions.*` |
| GET/POST/PATCH | `/finance/sponsors…` | `.sponsors.*` (contacts nested) |
| GET/POST/PATCH | `/finance/sponsorships…` | `.sponsorships.*` (split rules nested in the form) |
| GET/POST | `/finance/receipts…` | `.receipts.*` |
| GET | `/finance/receipts/{receipt}/split` | `.receipts.split` (proposal JSON) |
| POST | `/finance/receipts/{receipt}/submit` · `/post` · `/reject` | |
| GET | `/finance/requests` · POST `/finance/requests/{r}/approve` · `/reject` · `/changes` | `.requests.*` |
| GET/POST | `/finance/allocations…` · `/approve` | `.allocations.*` |
| GET | `/finance/expenses` · POST `/finance/expenses/{e}/approve` · `/reject` | `.expenses.*` |
| GET/POST | `/finance/payouts…` · `/submit` · `/approve` · `/second-approve` · `/paid` · `/cancel` | `.payouts.*` |
| GET | `/finance/periods` · POST `/finance/periods/{p}/close` | `.periods.*` |
| GET | `/finance/reports/{report}` | `.reports.show` (income, spending, payouts, budget-vs-actual, sponsor-statement); `?format=csv` |

**Workspace** (`/t/{team:slug}/budget`):

| Method | URI | Name |
| --- | --- | --- |
| GET | `/budget` | `workspace.budget.index` (team and project budgets, sources, recent movements) |
| GET/POST | `/budget/requests` · `/budget/requests/{r}` · POST `/submit` · `/cancel` | `workspace.budget.requests.*` |
| GET/POST | `/budget/expenses` · POST `/budget/expenses/{e}/submit` | `workspace.budget.expenses.*` |

**Account:**
- `GET /account/earnings`, named `account.earnings`, shows the user's own payouts with totals per year.

**Private files:**
- `GET /files/{media}` (`FileController`) streams private media after authorising against the owning model's policy.

## 6. Form Requests (highlights)

| Request | Rules |
| --- | --- |
| `TransactionRequest` | `date` required date; `from_account_id`, `to_account_id` required exists, different, same currency; `amount` required decimal:0,3 gt:0 (to minor units via the currency's scale); `rate_to_lyd` required_unless currency=LYD numeric gt:0; `description` required max 500; `reference` nullable max 120; `type` in Adjustment (manual only) |
| `SponsorshipRequest` | `sponsor_id` exists; `title` required; `kind` enum; `pledged` decimal; `currency`; dates; `rules` array: each has `account_id` exists (internal) and exactly one of `percent` (0.01..100) or `fixed`; Σ percent ≤ 100 (a closure rule) |
| `ReceiptRequest` | `sponsorship_id` nullable exists active; `source` enum; `payer_account_id` external; `date`; `amount` (or the in-kind items sum); `method` enum; `in_kind_items` required_if method=InKind |
| `SubmitReceiptRequest` | `lines` array min 1; `lines.*.account_id` internal; `lines.*.amount` gt 0; the sum equals the receipt amount exactly (compared in minor units) |
| `FundingRequestRequest` | `project_id` nullable, of the team; `amount` gt 0; `purpose` required max 1000; `breakdown` array; `source_account_id` in the funds offered to teams (`finance.request_sources` setting) |
| `ExpenseRequest` | `budget_account_id` that the user's context can spend from; `vendor_id` xor `vendor_name`; `date`; `amount`; `items` array min 1; `receipt` required file mimes:pdf,jpg,png max 10240 |
| `PayoutRequest` | `user_id` exists; `type` enum; `amount`; `currency`; `source_account_id` internal; `reason` required; `guardian_name` and `guardian_contact` required when the payee's age is < 18; `link_*` optional |
| `MarkPaidRequest` | `method` enum; `reference` required max 120; `paid_on` required date ≤ today; `proof` nullable file |

## 7. Pages and props

| Page | Props |
| --- | --- |
| `console/Finance/Dashboard.vue` | `balances: {account, balance: MoneyDTO, lyd: MoneyDTO}[]` grouped by type; `period: {income, spending}` per currency and in LYD; `pledgesOutstanding`; `pending: {transactions, receipts, requests, expenses, payouts}`; `topSponsors[]`, `topFunded[]` |
| `console/Finance/Accounts/Show.vue` | `account`, `statement` (paginated rows with a running balance, computed in SQL using a window function `SUM(...) OVER (ORDER BY date, id)`), `range` |
| `console/Finance/Receipts/Split.vue` | `receipt`, `proposal: {accountId, accountName, amount: MoneyDTO}[]`, `accounts` (internal, same currency); an editable table that shows the remaining amount and blocks submit until it is zero |
| `console/Finance/Sponsorships/Show.vue` | `sponsorship`, `pledged`, `received`, `outstanding`, `splits[]`, `spend[]` (from FundingTrace, labelled "estimated"), `receipts[]` |
| `console/Finance/Payouts/Index.vue` | paginated payouts, filterable by status, type, person and period; approval actions shown by `can` |
| `workspace/Budget/Index.vue` | `budgets: {account, allocated, spent, remaining, sources[]}[]` (team plus each project), `requests[]`, `expenses[]`, `can.{request, submitExpense}` |
| `account/Earnings.vue` | `payouts[]` (type, amount, status, paidOn, reference), `totalsByYear[]` |

## 8. Scheduled jobs
- `finance:ensure-periods` (daily) creates the current and the next month's periods.
- `finance:verify` (nightly) runs `LedgerService::verifyAll()`. Mismatches are logged, mailed to the super admin and shown on the dashboard. Nothing is fixed automatically.

## 9. Settings used (stored in `settings`, see 08)
- `finance.second_approval_threshold` = 5000000 minor units (5,000 LYD).
- `finance.prize_split` = `{team: 50, reinvestment: 50}`.
- `finance.request_sources` = [GeneralFund, ReinvestmentPool].
- `finance.department_id`: the department whose admins may override.

## 10. Tests
- `split: fixed rules first, percentages of the remainder, rounding never loses a dirham` (datasets, including 3-decimal LYD and odd amounts)
- `submitting a receipt requires lines that sum exactly to the receipt`
- `posting updates both balances and the sum of posted rows equals every cached balance`
- `posted transactions cannot be updated or deleted; reversal swaps the accounts and links both ways`
- `the maker cannot approve their own transaction, receipt, expense or payout`
- `internal accounts cannot go negative without an admin override reason`
- `transactions dated in a closed period are refused`
- `currency mismatch between the transaction and its accounts is refused`
- `concurrent posts on the same account keep the balance consistent` (two processes, lock ordering)
- `payouts at or above the threshold need a second, different approver`
- `payouts to under-18 members require guardian details`
- `a payout leaves the ledger only when marked paid with a reference`
- `team captains see only their own team's budgets; members see none`
- `My earnings lists only the signed-in user's payouts`
- `finance approval routes require a recent password confirmation and are blocked while impersonating`
- `private finance files are only served to authorised users`
