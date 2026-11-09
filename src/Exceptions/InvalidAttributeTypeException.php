<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * The properties column contains something that is not a JSON object or array.
 */
class InvalidAttributeTypeException extends PropertiesException
{
    public static function invalidJson(Model $model, string $column, string $reason): self
    {
        return new self(sprintf(
            'The column [%s] of [%s] does not contain valid JSON: %s',
            $column, $model::class, $reason,
        ));
    }

    public static function notAnArray(Model $model, string $column, mixed $value): self
    {
        return new self(sprintf(
            'The column [%s] of [%s] must contain a JSON object or array, [%s] given.',
            $column, $model::class, get_debug_type($value),
        ));
    }
}
