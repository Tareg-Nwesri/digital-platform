<?php

namespace App\Domain\Events\Services;

use Carbon\CarbonInterface;

/**
 * Which entry rules apply: a team applying, an organiser adding a team, or
 * an organiser approving an entry (LLD 06 §4).
 */
final readonly class EntryContext
{
    public const APPLY = 'apply';

    public const ADD = 'add';

    public const APPROVE = 'approve';

    private function __construct(
        public string $mode,
        public CarbonInterface $now,
        public ?int $excludeRegistrationId = null,
    ) {}

    public static function apply(): self
    {
        return new self(self::APPLY, now());
    }

    public static function add(): self
    {
        return new self(self::ADD, now());
    }

    public static function approve(?int $excludeRegistrationId = null): self
    {
        return new self(self::APPROVE, now(), $excludeRegistrationId);
    }

    public function excluding(int $registrationId): self
    {
        return new self($this->mode, $this->now, $registrationId);
    }

    public function isApply(): bool
    {
        return $this->mode === self::APPLY;
    }
}
