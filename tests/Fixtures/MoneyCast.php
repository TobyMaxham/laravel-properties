<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\Contracts\PropertyCast;

/**
 * Stores cents, returns "12.34 EUR".
 */
class MoneyCast implements PropertyCast
{
    public function __construct(private readonly string $currency = 'EUR') {}

    public function get(mixed $value, string $key, Model $model): ?string
    {
        return is_int($value) ? number_format($value / 100, 2, '.', '').' '.$this->currency : null;
    }

    public function set(mixed $value, string $key, Model $model): ?int
    {
        return is_numeric($value) ? (int) round((float) $value * 100) : null;
    }
}
