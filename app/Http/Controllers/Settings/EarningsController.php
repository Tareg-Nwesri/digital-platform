<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Services\PayoutService;
use App\Http\Controllers\Controller;
use App\Support\Money\MoneyFormatter;
use Brick\Money\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My earnings" (LLD 07 §5): the signed-in person's own payouts only. The
 * route has no id, so nobody can ask for someone else's.
 */
class EarningsController extends Controller
{
    public function __invoke(Request $request, PayoutService $payouts): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Earnings', [
            'payouts' => Payout::query()
                ->where('user_id', $user->getKey())
                ->whereIn('status', [PayoutStatus::Approved, PayoutStatus::Paid])
                ->latest('paid_on')->latest('id')
                ->get()
                ->map(fn (Payout $payout): array => [
                    'id' => $payout->getKey(),
                    'type' => $payout->type->value,
                    'amount' => MoneyFormatter::toArray($payout->amount),
                    'status' => $payout->status->value,
                    'reason' => $payout->reason,
                    'paidOn' => $payout->paid_on?->toDateString(),
                    'reference' => $payout->reference,
                ])
                ->all(),
            'totals' => array_map(fn (array $total): array => [
                'year' => $total['year'],
                'amount' => MoneyFormatter::toArray(Money::ofMinor($total['amountMinor'], $total['currency'])),
            ], $payouts->totalsByYear($user)),
        ]);
    }
}
