<?php

namespace App\Domain\Finance\Enums;

enum AccountType: string
{
    case GeneralFund = 'general_fund';
    case EarmarkedFund = 'earmarked_fund';
    case ReinvestmentPool = 'reinvestment_pool';
    case TeamBudget = 'team_budget';
    case ProjectBudget = 'project_budget';
    case EventBudget = 'event_budget';
    case ExternalSponsor = 'external_sponsor';
    case ExternalVendor = 'external_vendor';
    case ExternalPerson = 'external_person';
    case ExternalOther = 'external_other';

    /**
     * Internal accounts hold the foundation's money and cannot go negative
     * without an override. External accounts only mirror outside money.
     */
    public function isInternal(): bool
    {
        return ! in_array($this, [self::ExternalSponsor, self::ExternalVendor, self::ExternalPerson, self::ExternalOther], true);
    }

    public function label(): string
    {
        return __('finance.account_type.'.$this->value);
    }
}
