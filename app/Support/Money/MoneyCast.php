<?php

namespace App\Support\Money;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a pair of columns (integer minor units + ISO currency) to a Brick Money object.
 *
 * Usage: 'price' => MoneyCast::class.':price_minor,currency'
 *
 * @implements CastsAttributes<Money|null, Money|null>
 */
final class MoneyCast implements CastsAttributes
{
    public function __construct(
        private readonly string $amountColumn,
        private readonly string $currencyColumn = 'currency',
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if (! isset($attributes[$this->amountColumn], $attributes[$this->currencyColumn])) {
            return null;
        }

        return Money::ofMinor((int) $attributes[$this->amountColumn], (string) $attributes[$this->currencyColumn]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$this->amountColumn => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException('Expected an instance of '.Money::class.'.');
        }

        return [
            $this->amountColumn => $value->getMinorAmount()->toInt(),
            $this->currencyColumn => $value->getCurrency()->getCurrencyCode(),
        ];
    }
}
