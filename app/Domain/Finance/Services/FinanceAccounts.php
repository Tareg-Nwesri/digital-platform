<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Models\FinanceAccount;
use Illuminate\Database\Eloquent\Model;
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
}
