<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Support;

use UnitEnum;
use BackedEnum;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\Contracts\PropertyCast;
use TobyMaxham\LaravelProperties\PropertyCasts\DateCast;
use TobyMaxham\LaravelProperties\PropertyCasts\EnumCast;
use TobyMaxham\LaravelProperties\PropertyCasts\EncryptedCast;
use TobyMaxham\LaravelProperties\PropertyCasts\PrimitiveCast;

/**
 * Applies the `propertyCasts()` of a model to property values, also for nested keys.
 *
 * @internal
 */
final class PropertyCaster
{
    /**
     * Resolved casts by definition string, shared by all models.
     *
     * @var array<string, PropertyCast>
     */
    private static array $resolved = [];

    /** @var array<string, PropertyCast> */
    private readonly array $casts;

    /**
     * @param  array<array-key, mixed>  $definitions
     */
    public function __construct(private readonly Model $model, array $definitions)
    {
        $casts = [];

        foreach ($definitions as $key => $definition) {
            $casts[(string) $key] = $this->resolve((string) $key, $definition);
        }

        $this->casts = $casts;
    }

    public function isEmpty(): bool
    {
        return [] === $this->casts;
    }

    public function castFor(string $key): ?PropertyCast
    {
        return $this->casts[$key] ?? null;
    }

    /**
     * Keys of all encrypted properties.
     *
     * @return list<string>
     */
    public function encryptedKeys(): array
    {
        return array_keys(array_filter($this->casts, fn (PropertyCast $cast) => $cast instanceof EncryptedCast));
    }

    /**
     * The encrypted key that `$path` is, is inside of, or contains. `null` if none.
     */
    public function encryptedKeyFor(string $path): ?string
    {
        foreach ($this->encryptedKeys() as $key) {
            if ($key === $path || str_starts_with($path, $key.'.') || str_starts_with($key, $path.'.')) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Whether the stored value at `$path` already contains `$value` (only checked for encrypted properties).
     */
    public function contains(string $path, mixed $stored, mixed $value): bool
    {
        $cast = $this->casts[$path] ?? null;

        return $cast instanceof EncryptedCast && $cast->contains($stored, $value, $path, $this->model);
    }

    /**
     * Cast a value read at `$path` (`''` for all properties).
     */
    public function get(string $path, mixed $value): mixed
    {
        return $this->apply($path, $value, fn (PropertyCast $cast, mixed $item, string $key) => $cast->get($item, $key, $this->model));
    }

    /**
     * Prepare a value written at `$path` (`''` for all properties) for the storage.
     */
    public function set(string $path, mixed $value): mixed
    {
        return $this->apply($path, $value, fn (PropertyCast $cast, mixed $item, string $key) => $cast->set($item, $key, $this->model));
    }

    /**
     * Casts below the path are applied first (`address.country`), then the cast of the path itself (`address`).
     *
     * @param  callable(PropertyCast, mixed, string): mixed  $callback
     */
    private function apply(string $path, mixed $value, callable $callback): mixed
    {
        if ($this->isEmpty()) {
            return $value;
        }

        $prefix = '' === $path ? '' : $path.'.';

        // Deepest keys first, so a cast of a parent gets the already cast children.
        $nested = array_filter(array_keys($this->casts), fn (string $key) => '' !== $prefix ? str_starts_with($key, $prefix) : true);
        usort($nested, fn (string $a, string $b) => substr_count($b, '.') <=> substr_count($a, '.'));

        foreach ($nested as $key) {
            $relative = substr($key, strlen($prefix));

            if (is_array($value) && Arr::has($value, $relative)) {
                Arr::set($value, $relative, $callback($this->casts[$key], Arr::get($value, $relative), $key));
            }
        }

        if ('' !== $path && isset($this->casts[$path])) {
            $value = $callback($this->casts[$path], $value, $path);
        }

        return $value;
    }

    private function resolve(string $key, mixed $definition): PropertyCast
    {
        if ($definition instanceof PropertyCast) {
            return $definition;
        }

        if (! is_string($definition) || '' === $definition) {
            throw $this->invalid($key, get_debug_type($definition));
        }

        return self::$resolved[$definition] ??= $this->resolveString($key, $definition);
    }

    private function resolveString(string $key, string $definition): PropertyCast
    {
        $parts = explode(':', $definition, 2);
        $type = $parts[0];
        $arguments = $parts[1] ?? null;

        if ('encrypted' === $type) {
            return new EncryptedCast(null === $arguments ? null : $this->resolveString($key, $arguments));
        }

        if (isset(PrimitiveCast::TYPES[$type])) {
            return new PrimitiveCast($type, null === $arguments ? null : (int) $arguments);
        }

        if (in_array($type, DateCast::TYPES, true)) {
            return new DateCast($type, $arguments);
        }

        if (! class_exists($type)) {
            throw $this->invalid($key, $definition);
        }

        return match (true) {
            is_subclass_of($type, BackedEnum::class) => new EnumCast($type),
            is_subclass_of($type, UnitEnum::class)   => throw new InvalidArgumentException(sprintf(
                'The enum [%s] of the property [%s] on [%s] must be a backed enum to be stored.', $type, $key, $this->model::class,
            )),
            is_subclass_of($type, PropertyCast::class) => new $type(...(null === $arguments ? [] : explode(',', $arguments))),
            default                                    => throw $this->invalid($key, $definition),
        };
    }

    private function invalid(string $key, string $definition): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'Unknown cast [%s] for the property [%s] on [%s].', $definition, $key, $this->model::class,
        ));
    }
}
