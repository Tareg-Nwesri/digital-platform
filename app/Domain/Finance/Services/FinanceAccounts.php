<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Teams\Models\Team;
use App\Domain\Work\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Finds or creates accounts (LLD 07 §1): one fund per type and currency,
 * external mirrors created lazily per counterparty and currency.
 */
final class FinanceAccounts
{
    private const FUND_NAMES = [
        'general_fund' => ['General fund', 'الصندوق العام'],
        'reinvestment_pool' => ['Reinvestment pool', 'صندوق إعادة الاستثمار'],
    ];

    public function fund(AccountType $type, string $currency): FinanceAccount
    {
        if (! array_key_exists($type->value, self::FUND_NAMES)) {
            throw new InvalidArgumentException("{$type->value} is not a shared fund.");
        }

        [$english, $arabic] = self::FUND_NAMES[$type->value];

        return FinanceAccount::query()->firstOrCreate(
            ['type' => $type, 'owner_type' => null, 'owner_id' => null, 'currency' => $currency],
            ['name_en' => $english, 'name_ar' => $arabic],
        );
    }

    /**
     * The outside-world mirror for a sponsor, vendor or person. With an owner
     * model it is keyed by that model; otherwise by name.
     */
    public function external(AccountType $type, string $name, string $currency, ?Model $owner = null): FinanceAccount
    {
        if ($type->isInternal()) {
            throw new InvalidArgumentException("{$type->value} is not an external account type.");
        }

        return FinanceAccount::query()->firstOrCreate(
            [
                'type' => $type,
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
                'currency' => $currency,
                ...($owner === null ? ['name_en' => $name] : []),
            ],
            ['name_en' => $name],
        );
    }

    /**
     * A named internal account such as an earmarked fund or an event budget.
     */
    public function create(AccountType $type, string $nameEn, ?string $nameAr, string $currency, ?string $purpose = null, ?Model $owner = null): FinanceAccount
    {
        return FinanceAccount::query()->create([
            'type' => $type,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'currency' => $currency,
            'is_restricted' => $type === AccountType::EarmarkedFund,
            'purpose' => $purpose,
        ]);
    }

    /**
     * The budget account of a team or a project in a currency, created on
     * first use (LLD 07 §3).
     */
    public function budgetFor(Team|Project $owner, string $currency): FinanceAccount
    {
        $type = $owner instanceof Team ? AccountType::TeamBudget : AccountType::ProjectBudget;

        return DB::transaction(function () use ($owner, $currency, $type): FinanceAccount {
            $existing = FinanceAccount::query()
                ->where('type', $type)
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $name = $owner instanceof Team ? $owner->name : (string) $owner->title_en;

            return $this->create($type, $name, $owner instanceof Team ? $owner->name : $owner->title_ar, $currency, null, $owner);
        });
    }
}
