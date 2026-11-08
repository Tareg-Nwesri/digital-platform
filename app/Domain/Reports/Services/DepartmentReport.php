<?php

namespace App\Domain\Reports\Services;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Content\Enums\MessageStatus;
use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Content\Models\Post;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Organisation\Models\Department;
use App\Models\Activity;
use App\Models\User;
use BackedEnum;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Numbers for one department and period (LLD 08 §3). Each area appears
 * only when it is in the department's ceiling.
 */
final class DepartmentReport
{
    /**
     * @return array<string, mixed>
     */
    public function forDepartment(Department $department, CarbonInterface $from, CarbonInterface $to): array
    {
        $areas = $department->ceilingNames()->map(fn (string $name): string => explode('.', $name)[0])->unique()->values()->all();
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $report = ['areas' => $areas];

        if (in_array('events', $areas, true)) {
            $events = Event::query()->where('department_id', $department->getKey());
            $report['events'] = [
                'byStatus' => $this->countBy((clone $events)->where(fn ($query) => $query
                    ->whereBetween('starts_at', $range)
                    ->orWhereIn('status', [EventStatus::Open, EventStatus::Closed, EventStatus::Running])), 'status', EventStatus::cases()),
                'registrations' => $this->countBy(EventRegistration::query()->whereIn('event_id', (clone $events)->select('id'))->whereBetween('created_at', $range), 'status', RegistrationStatus::cases()),
            ];
        }

        if (in_array('catalogue', $areas, true)) {
            $report['catalogue'] = [
                'suggested' => Part::query()->whereNotNull('suggested_by_team_id')->whereBetween('created_at', $range)->count()
                    + Vendor::query()->whereNotNull('suggested_by_team_id')->whereBetween('created_at', $range)->count(),
                'approved' => $this->reviewed(CatalogueStatus::Approved, $range),
                'rejected' => $this->reviewed(CatalogueStatus::Rejected, $range),
            ];
        }

        if (in_array('content', $areas, true)) {
            $report['content'] = [
                'published' => Post::query()->where('department_id', $department->getKey())->where('status', PostStatus::Published)->whereBetween('published_at', $range)->count(),
                'drafts' => Post::query()->where('department_id', $department->getKey())->whereIn('status', [PostStatus::Draft, PostStatus::InReview])->count(),
            ];
        }

        if (in_array('inbox', $areas, true)) {
            $messages = ContactMessage::query()->where('department_id', $department->getKey())->whereBetween('created_at', $range);
            $byStatus = $this->countBy($messages, 'status', MessageStatus::cases());
            $report['inbox'] = ['new' => $byStatus['new'], 'inProgress' => $byStatus['in_progress'], 'closed' => $byStatus['closed']];
        }

        if (in_array('finance', $areas, true)) {
            $report['finance'] = $this->finance($range);
        }

        return $report;
    }

    /**
     * What the department's members did in the period, newest first.
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    public function activity(Department $department, CarbonInterface $from, CarbonInterface $to, int $perPage = 20): LengthAwarePaginator
    {
        return Activity::query()
            ->with('causer')
            ->whereIn('causer_id', $department->members()->select('users.id'))
            ->where('causer_type', (new User)->getMorphClass())
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<BackedEnum>  $cases
     * @return array<string, int>
     */
    private function countBy(Builder $query, string $column, array $cases): array
    {
        $counts = (clone $query)->toBase()->reorder()->selectRaw("{$column} as bucket, count(*) as total")->groupBy($column)->pluck('total', 'bucket');

        return collect($cases)->mapWithKeys(fn (BackedEnum $case): array => [(string) $case->value => (int) ($counts[$case->value] ?? 0)])->all();
    }

    /**
     * @param  array{0: CarbonInterface, 1: CarbonInterface}  $range
     */
    private function reviewed(CatalogueStatus $status, array $range): int
    {
        return Part::query()->where('status', $status)->whereNotNull('suggested_by_team_id')->whereBetween('reviewed_at', $range)->count()
            + Vendor::query()->where('status', $status)->whereNotNull('suggested_by_team_id')->whereBetween('reviewed_at', $range)->count();
    }

    /**
     * Income into and spending out of foundation accounts, in LYD.
     *
     * @param  array{0: CarbonInterface, 1: CarbonInterface}  $range
     * @return array{income: string, spending: string}
     */
    private function finance(array $range): array
    {
        $internal = FinanceAccount::query()->internal()->select('id');
        $posted = fn () => LedgerTransaction::query()->where('status', TransactionStatus::Posted)
            ->whereBetween('date', [$range[0]->toDateString(), $range[1]->toDateString()]);

        $toLyd = fn ($rows): Money => $rows->reduce(
            fn (Money $sum, LedgerTransaction $transaction): Money => $sum->plus(
                Money::of($transaction->amount->getAmount()->multipliedBy($transaction->rate_to_lyd), 'LYD', roundingMode: RoundingMode::HalfUp),
            ),
            Money::zero('LYD'),
        );

        $income = $posted()->where('type', TransactionType::Income)->whereIn('to_account_id', $internal)->get();
        $spending = $posted()->whereIn('type', [TransactionType::Expense, TransactionType::Payout])->whereIn('from_account_id', $internal)->get();

        return [
            'income' => (string) $toLyd($income)->getAmount(),
            'spending' => (string) $toLyd($spending)->getAmount(),
        ];
    }
}
