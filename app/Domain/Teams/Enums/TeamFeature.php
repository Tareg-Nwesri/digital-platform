<?php

namespace App\Domain\Teams\Enums;

/**
 * Team features (SRS Appendix B.2). The value is the Pennant feature name.
 */
enum TeamFeature: string
{
    case MembersMax = 'members.max';
    case ProjectsCreate = 'projects.create';
    case ProjectsPublish = 'projects.publish';
    case RobotsCreate = 'robots.create';
    case PartsAccess = 'parts.access';
    case PartsSuggest = 'parts.suggest';
    case EventsRegister = 'events.register';
    case PublicProfile = 'public.profile';
    case BudgetRequest = 'budget.request';

    public function isLimit(): bool
    {
        return $this === self::MembersMax;
    }

    /**
     * Value used when a tier does not define the feature.
     */
    public function fallback(): bool|int
    {
        return match ($this) {
            self::MembersMax => 8,
            self::ProjectsCreate, self::RobotsCreate, self::PartsAccess => true,
            default => false,
        };
    }

    public function normalize(mixed $value): bool|int
    {
        return $this->isLimit() ? max(0, (int) $value) : (bool) $value;
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
