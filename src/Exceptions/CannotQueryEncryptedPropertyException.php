<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Encrypted values can not be compared in SQL: every encryption of the same value is different.
 */
class CannotQueryEncryptedPropertyException extends PropertiesException
{
    public static function forProperty(Model $model, string $key, string $encryptedKey): self
    {
        return new self(sprintf(
            'The property [%s] of [%s] can not be queried, because [%s] is encrypted.',
            $key, $model::class, $encryptedKey,
        ));
    }

    public static function forColumn(Model $model, string $column): self
    {
        return new self(sprintf(
            'The properties of [%s] can not be queried, because the column [%s] is encrypted.',
            $model::class, $column,
        ));
    }
}
