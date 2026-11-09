<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * A property has the wrong type for the operation, e.g. `incrementProperty()` on a string.
 */
class InvalidPropertyValueException extends PropertiesException
{
    public static function expected(Model $model, string $key, string $expected, mixed $value): self
    {
        return new self(sprintf(
            'The property [%s] of [%s] must be %s, [%s] given.',
            $key, $model::class, $expected, get_debug_type($value),
        ));
    }

    public static function invalidValue(Model $model, string $key, string $reason): self
    {
        return new self(sprintf('The property [%s] of [%s] is invalid: %s.', $key, $model::class, $reason));
    }
}
