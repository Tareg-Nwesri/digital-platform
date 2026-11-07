<?php

namespace App\Domain\Events\Services;

/**
 * One broken entry rule: a stable code and a translated message.
 */
final readonly class RuleViolation
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function of(string $code, array $replace = []): self
    {
        return new self($code, __('events.rules.'.$code, $replace));
    }
}
