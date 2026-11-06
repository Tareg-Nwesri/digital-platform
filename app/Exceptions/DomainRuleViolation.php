<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;
use RuntimeException;

final class DomainRuleViolation extends RuntimeException
{
    /**
     * @param  array<string, list<string>|string>  $violations  field or rule code => message(s)
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(implode(' ', array_map(fn (array|string $message): string => implode(' ', (array) $message), $violations)));
    }

    /**
     * @param  array<string, list<string>|string>  $violations
     */
    public static function with(array $violations): self
    {
        return new self($violations);
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages($this->violations);
    }
}
