<?php

namespace App\Http\Controllers\Console;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Content\Enums\MessageStatus;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Enums\RequestStatus;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\FundingRequest;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Models\Receipt;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Work\Enums\ProjectStatus;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\Project;
use App\Domain\Work\Models\Robot;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Support\Activity\ActivityLabel;
use App\Support\Money\MoneyFormatter;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The super admin's working dashboard: what waits for someone, how the
 * next events are filling up, how registrations are coming in, where the
 * money stands and how the community is growing.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/Dashboard', [
            'attention' => $this->attention(),
            'events' => $this->upcomingEvents(),
            'registrations' => $this->registrationTrend(),
            'finance' => $this->finance(),
            'community' => $this->community(),
            'activity' => $this->activity(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, hint: string, count: int, href: string, tone: string}>
     */
    private function attention(): array
    {
        $fromWebsite = EventRegistration::query()->where('status', RegistrationStatus::Pending)->where('source', RegistrationSource::Public)->count();
        $suggestions = collect([Part::class, Vendor::class, VendorOffer::class])
            ->sum(fn (string $model): int => $model::query()->where('status', CatalogueStatus::Suggested)->count());
        $approvals = LedgerTransaction::query()->where('status', TransactionStatus::Submitted)->count()
            + Receipt::query()->where('status', DocumentStatus::Submitted)->count()
            + Payout::query()->whereIn('status', [PayoutStatus::Submitted, PayoutStatus::Approved])->count();

        return [
            $this->task('registrations', EventRegistration::query()->where('status', RegistrationStatus::Pending)->count(), route('console.events.index'), 'Event registrations to review', $fromWebsite > 0 ? __(':count from the website', ['count' => $fromWebsite]) : __('Approve, waitlist or reject'), 'urgent'),
            $this->task('teams', Team::query()->where('status', TeamStatus::Pending)->count(), route('console.teams.index', ['filter' => ['status' => TeamStatus::Pending->value]]), 'Teams waiting for approval', __('New teams cannot sign in until approved'), 'urgent'),
            $this->task('funding', FundingRequest::query()->where('status', RequestStatus::Submitted)->count(), route('console.finance.budgets.index'), 'Funding requests', __('Teams asking for money'), 'finance'),
            $this->task('expenses', Expense::query()->where('status', DocumentStatus::Submitted)->count(), route('console.finance.budgets.index'), 'Expenses to approve', __('Spending with receipts'), 'finance'),
            $this->task('approvals', $approvals, route('console.finance.approvals'), 'Finance approvals', __('Transactions, receipts and payouts'), 'finance'),
            $this->task('suggestions', $suggestions, route('console.catalogue.suggestions.index'), 'Catalogue suggestions', __('Parts, vendors and prices from teams'), 'normal'),
            $this->task('messages', ContactMessage::query()->where('status', MessageStatus::New)->count(), route('console.inbox.index'), 'New messages', __('From the contact form'), 'normal'),
        ];
    }

    /**
     * @return array{key: string, label: string, hint: string, count: int, href: string, tone: string}
     */
    private function task(string $key, int $count, string $href, string $label, string $hint, string $tone): array
    {
        return ['key' => $key, 'label' => __($label), 'hint' => $hint, 'count' => $count, 'href' => $href, 'tone' => $tone];
    }

    /**
     * Events still ahead or running, nearest first, with how they fill up.
     *
     * @return list<array<string, mixed>>
     */
    private function upcomingEvents(): array
    {
        $today = now()->startOfDay();

        return Event::query()
            ->whereIn('status', [EventStatus::Open, EventStatus::Closed, EventStatus::Running])
            ->where('ends_at', '>=', now())
            ->withCount([
                'registrations as approved_count' => fn (Builder $query) => $query->where('status', RegistrationStatus::Approved),
                'registrations as pending_count' => fn (Builder $query) => $query->where('status', RegistrationStatus::Pending),
                'registrations as waitlisted_count' => fn (Builder $query) => $query->where('status', RegistrationStatus::Waitlisted),
                'registrations as website_count' => fn (Builder $query) => $query->where('source', RegistrationSource::Public)->whereIn('status', RegistrationStatus::active()),
            ])
            ->orderBy('starts_at')
            ->limit(4)
            ->get()
            ->map(function (Event $event) use ($today): array {
                $closes = $event->registration_closes_at ?? $event->starts_at;

                return [
                    'slug' => $event->slug,
                    'title' => (string) $event->localized('title'),
                    'status' => $event->status->value,
                    'startsAt' => $event->starts_at->toIso8601String(),
                    'timezone' => $event->timezone,
                    'daysToStart' => (int) $today->diffInDays($event->starts_at->copy()->startOfDay(), false),
                    'daysToClose' => $event->isAcceptingApplications() ? (int) $today->diffInDays($closes->copy()->startOfDay(), false) : null,
                    'capacity' => $event->capacity,
                    'approved' => (int) $event->approved_count,
                    'pending' => (int) $event->pending_count,
                    'waitlisted' => (int) $event->waitlisted_count,
                    'fromWebsite' => (int) $event->website_count,
                ];
            })
            ->all();
    }

    /**
     * New registrations per day over the last 14 days (today included).
     *
     * @return array{days: list<array{date: string, count: int}>, total: int, fromWebsite: int}
     */
    private function registrationTrend(): array
    {
        $first = now()->startOfDay()->subDays(13);
        $counts = EventRegistration::query()
            ->where('created_at', '>=', $first)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = collect(range(0, 13))->map(function (int $offset) use ($first, $counts): array {
            $date = $first->copy()->addDays($offset)->toDateString();

            return ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        });

        return [
            'days' => $days->all(),
            'total' => (int) $days->sum('count'),
            'fromWebsite' => EventRegistration::query()->where('created_at', '>=', $first)->where('source', RegistrationSource::Public)->count(),
        ];
    }

    /**
     * @return array{funds: list<array{name: string, balance: string}>, monthIn: list<string>, monthOut: list<string>, teamBudgets: list<string>}
     */
    private function finance(): array
    {
        $month = now()->startOfMonth()->toDateString();
        $posted = fn (array $types): array => LedgerTransaction::query()
            ->where('status', TransactionStatus::Posted)
            ->whereIn('type', $types)
            ->where('date', '>=', $month)
            ->select('currency', DB::raw('SUM(amount_minor) as total'))
            ->groupBy('currency')
            ->get()
            ->map(fn ($row): string => MoneyFormatter::format(Money::ofMinor((int) $row->total, $row->currency)))
            ->all();

        return [
            'funds' => FinanceAccount::query()
                ->whereIn('type', [AccountType::GeneralFund, AccountType::EarmarkedFund, AccountType::ReinvestmentPool])
                ->where('is_archived', false)
                ->orderBy('type')
                ->get()
                ->map(fn (FinanceAccount $account): array => [
                    'name' => (string) $account->localized('name'),
                    'balance' => MoneyFormatter::format($account->balance()),
                ])
                ->all(),
            'monthIn' => $posted([TransactionType::Income]),
            'monthOut' => $posted([TransactionType::Expense, TransactionType::Payout]),
            'teamBudgets' => FinanceAccount::query()
                ->whereIn('type', [AccountType::TeamBudget, AccountType::ProjectBudget])
                ->select('currency', DB::raw('SUM(balance_minor) as total'))
                ->groupBy('currency')
                ->get()
                ->map(fn ($row): string => MoneyFormatter::format(Money::ofMinor((int) $row->total, $row->currency)))
                ->all(),
        ];
    }

    /**
     * @return array{activeTeams: int, pendingTeams: int, members: int, robots: int, publishedProjects: int, newTeamsThisMonth: int}
     */
    private function community(): array
    {
        return [
            'activeTeams' => Team::query()->where('status', TeamStatus::Active)->count(),
            'pendingTeams' => Team::query()->where('status', TeamStatus::Pending)->count(),
            'members' => (int) DB::table('team_members')
                ->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->where('teams.status', TeamStatus::Active->value)
                ->whereNull('teams.deleted_at')
                ->distinct()
                ->count('team_members.user_id'),
            'robots' => Robot::query()->where('status', '!=', RobotStatus::Retired)->count(),
            'publishedProjects' => Project::query()->where('status', ProjectStatus::Published)->count(),
            'newTeamsThisMonth' => Team::query()->where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /**
     * @return list<array{id: int, description: string, area: string|null, causer: string|null, at: string|null}>
     */
    private function activity(): array
    {
        return Activity::query()->with('causer')->latest('id')->limit(8)->get()
            ->map(fn (Activity $entry): array => [
                'id' => $entry->getKey(),
                'description' => ActivityLabel::for($entry),
                'area' => ActivityLabel::area($entry->log_name),
                'causer' => $entry->causer?->getAttribute('name'),
                'at' => $entry->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
