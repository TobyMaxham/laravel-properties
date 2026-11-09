<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cast for a single property, used in `propertyCasts()` of a model.
 *
 * ```php
 * protected function propertyCasts(): array
 * {
 *     return ['price' => MoneyCast::class, 'total' => MoneyCast::class.':EUR', 'other' => new MoneyCast('USD')];
 * }
 * ```
 *
 * Arguments after `:` are passed to the constructor, separated by `,`.
 */
interface PropertyCast
{
    /**
     * Convert the stored value into the value returned by `getProperty()`.
     *
     * @param  mixed  $value  the stored (JSON) value, or the default of the model
     */
    public function get(mixed $value, string $key, Model $model): mixed;

    /**
     * Convert a value into something that can be stored as JSON (scalar, array or `null`).
     */
    public function set(mixed $value, string $key, Model $model): mixed;
}
