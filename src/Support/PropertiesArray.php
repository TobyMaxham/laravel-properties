<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Support;

use stdClass;
use Traversable;
use JsonException;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Array helpers used by the trait and the cast.
 *
 * @internal
 */
final class PropertiesArray
{
    /**
     * Convert a value of the properties column (array, ArrayObject, Collection, ...) to a plain array.
     * Returns `null` if the value can not be converted.
     *
     * @return array<array-key, mixed>|null
     */
    public static function normalize(mixed $value): ?array
    {
        return match (true) {
            null === $value               => [],
            is_array($value)              => $value,
            $value instanceof Arrayable   => $value->toArray(),
            $value instanceof Traversable => iterator_to_array($value),
            $value instanceof stdClass    => self::fromStdClass($value),
            default                       => null,
        };
    }

    /**
     * Merge the stored properties into the defaults.
     * Associative arrays are merged recursively, lists replace the default.
     *
     * @param  array<array-key, mixed>  $defaults
     * @param  array<array-key, mixed>  $stored
     * @return array<array-key, mixed>
     */
    public static function mergeDefaults(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])
                && self::isAssoc($value) && self::isAssoc($defaults[$key])) {
                $value = self::mergeDefaults($defaults[$key], $value);
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    /**
     * Compare two property arrays, ignoring the order of keys (databases may reorder JSON keys).
     *
     * @param  array<array-key, mixed>  $first
     * @param  array<array-key, mixed>  $second
     */
    public static function equals(array $first, array $second): bool
    {
        return self::sortKeys($first) === self::sortKeys($second);
    }

    /**
     * Encode the properties as JSON. An empty array is stored as `{}`.
     *
     * @param  array<array-key, mixed>  $properties
     *
     * @throws JsonException
     */
    public static function toJson(array $properties): string
    {
        if ([] === $properties) {
            return '{}';
        }

        return json_encode(
            $properties,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /**
     * Empty arrays count as associative, so `[]` can be merged with defaults.
     *
     * @param  array<array-key, mixed>  $value
     */
    private static function isAssoc(array $value): bool
    {
        return [] === $value || ! array_is_list($value);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function sortKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortKeys($item);
            }
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function fromStdClass(stdClass $value): array
    {
        /** @var array<array-key, mixed> */
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
}
