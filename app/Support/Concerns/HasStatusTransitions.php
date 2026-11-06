<?php

namespace App\Support\Concerns;

use App\Exceptions\InvalidTransition;
use BackedEnum;

/**
 * Models using this trait must implement allowedTransitions(): array<string, list<BackedEnum>>,
 * keyed by the current status value.
 */
trait HasStatusTransitions
{
    /**
     * @return array<string, list<BackedEnum>>
     */
    abstract public static function allowedTransitions(): array;

    public function canTransitionTo(BackedEnum $to, string $attribute = 'status'): bool
    {
        $current = $this->getAttribute($attribute);
        $key = $current instanceof BackedEnum ? (string) $current->value : (string) $current;

        return in_array($to, static::allowedTransitions()[$key] ?? [], true);
    }

    public function transitionTo(BackedEnum $to, string $attribute = 'status'): void
    {
        if (! $this->canTransitionTo($to, $attribute)) {
            $current = $this->getAttribute($attribute);

            throw InvalidTransition::between($current instanceof BackedEnum ? $current : null, $to);
        }

        $this->setAttribute($attribute, $to);
    }
}
