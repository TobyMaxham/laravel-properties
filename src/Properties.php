<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties;

use Countable;
use ArrayAccess;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use TobyMaxham\LaravelProperties\Contracts\PropertyCast;
use TobyMaxham\LaravelProperties\Support\PropertyCaster;
use TobyMaxham\LaravelProperties\Support\PropertiesArray;
use TobyMaxham\LaravelProperties\Migrations\PropertiesMigrator;
use TobyMaxham\LaravelProperties\Exceptions\InvalidAttributeTypeException;
use TobyMaxham\LaravelProperties\Exceptions\InvalidPropertyValueException;
use TobyMaxham\LaravelProperties\Exceptions\CannotQueryEncryptedPropertyException;

/**
 * Fluent access to the properties of a model, returned by `$model->props()`.
 * All changes are written to the model attribute, the model is not saved.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 * @implements Arrayable<array-key, mixed>
 */
class Properties implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable
{
    private readonly PropertyCaster $caster;

    /**
     * @param  array<array-key, mixed>  $defaults
     * @param  array<array-key, mixed>  $casts  see `propertyCasts()` of the trait
     * @param  bool  $encryptedColumn  whether the whole column is encrypted
     */
    public function __construct(
        protected readonly Model $model,
        protected readonly string $column = 'properties',
        protected readonly array $defaults = [],
        protected readonly array $casts = [],
        protected readonly bool $encryptedColumn = false,
    ) {
        // Resolve the casts right away, so an invalid configuration fails early.
        $this->caster = new PropertyCaster($this->model, $this->casts);

        foreach ($this->caster->encryptedKeys() as $key) {
            if (Arr::has($this->defaults, $key)) {
                throw new InvalidArgumentException(sprintf(
                    'The encrypted property [%s] of [%s] can not have a default, it would be stored in plain text in the code.',
                    $key, $this->model::class,
                ));
            }
        }
    }

    /**
     * Change the properties of all rows of a table (plain JSON), e.g. in a migration.
     */
    public static function table(string $table, string $column = 'properties', ?string $connection = null, string $primaryKey = 'id'): PropertiesMigrator
    {
        return PropertiesMigrator::forTable($table, $column, $connection, $primaryKey);
    }

    /**
     * Change the properties of all rows of a model, with its casts and encryption.
     *
     * @param  class-string  $model
     */
    public static function for(string $model): PropertiesMigrator
    {
        return PropertiesMigrator::forModel($model);
    }

    public function model(): Model
    {
        return $this->model;
    }

    public function column(): string
    {
        return $this->column;
    }

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    /**
     * Get a property with "dot" notation. Falls back to the defaults, then to `$default`.
     * The property casts are applied, `$default` is returned as it is.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $properties = $this->merged();

        if (! Arr::has($properties, $key)) {
            return value($default);
        }

        return $this->caster()->get($key, Arr::get($properties, $key));
    }

    /**
     * Whether the property is stored or has a default.
     */
    public function has(string $key): bool
    {
        return Arr::has($this->merged(), $key);
    }

    /**
     * Stored properties merged into the defaults, with the property casts applied.
     *
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        /** @var array<array-key, mixed> */
        return $this->caster()->get('', $this->merged());
    }

    /**
     * Only the stored properties as they are stored (without defaults and casts).
     *
     * @return array<array-key, mixed>
     */
    public function stored(): array
    {
        $value = $this->model->getAttribute($this->column);

        return PropertiesArray::normalize($value)
            ?? throw InvalidAttributeTypeException::notAnArray($this->model, $this->column, $value);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function casts(): array
    {
        return $this->casts;
    }

    /**
     * The resolved cast of a property, `null` without cast.
     */
    public function castFor(string $key): ?PropertyCast
    {
        return $this->caster->castFor($key);
    }

    /**
     * Keys of the encrypted properties (casts `encrypted` / `encrypted:...`).
     *
     * @return list<string>
     */
    public function encrypted(): array
    {
        return $this->caster->encryptedKeys();
    }

    /**
     * Whether the property can be used in a database query.
     * `$keyOnly`: only the existence of the key is checked (works for encrypted properties).
     */
    public function isQueryable(string $key, bool $keyOnly = false): bool
    {
        try {
            $this->ensureQueryable($key, $keyOnly);
        } catch (CannotQueryEncryptedPropertyException) {
            return false;
        }

        return true;
    }

    /**
     * @throws CannotQueryEncryptedPropertyException
     */
    public function ensureQueryable(string $key, bool $keyOnly = false): void
    {
        if ($this->encryptedColumn) {
            throw CannotQueryEncryptedPropertyException::forColumn($this->model, $this->column);
        }

        if (! $keyOnly && null !== ($encrypted = $this->caster->encryptedKeyFor($key))) {
            throw CannotQueryEncryptedPropertyException::forProperty($this->model, $key, $encrypted);
        }
    }

    /**
     * Convert a value into the stored form, using the property casts (e.g. an enum into its value).
     * Use `''` as key to convert all properties.
     */
    public function toStorage(string $key, mixed $value): mixed
    {
        return $this->caster()->set($key, $value);
    }

    /**
     * @param  string|array<array-key, string>  $keys
     * @return array<array-key, mixed>
     */
    public function only(string|array $keys): array
    {
        $properties = $this->all();
        $result = [];

        foreach ((array) $keys as $key) {
            if (Arr::has($properties, $key)) {
                Arr::set($result, $key, Arr::get($properties, $key));
            }
        }

        return $result;
    }

    /**
     * @param  string|array<array-key, string>  $keys
     * @return array<array-key, mixed>
     */
    public function except(string|array $keys): array
    {
        return Arr::except($this->all(), $keys);
    }

    /**
     * @return Collection<array-key, mixed>
     */
    public function collect(): Collection
    {
        return new Collection($this->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Writing (the model is not saved)
    |--------------------------------------------------------------------------
    */

    /**
     * Set one property (`set('theme', 'dark')`) or several (`set(['theme' => 'dark', 'ui.size' => 'lg'])`).
     * Keys may use "dot" notation, other properties are kept.
     *
     * @param  string|array<array-key, mixed>  $key
     */
    public function set(string|array $key, mixed $value = null): static
    {
        $values = is_array($key) ? $key : [$key => $value];
        $properties = $this->stored();

        foreach ($values as $name => $item) {
            $name = (string) $name;

            // Keep the old encryption of an unchanged value, so the model does not become dirty.
            if (Arr::has($properties, $name) && $this->caster->contains($name, Arr::get($properties, $name), $item)) {
                continue;
            }

            Arr::set($properties, $name, $this->toStorage($name, $item));
        }

        return $this->write($properties);
    }

    /**
     * Set several properties and keep the others.
     *
     * @param  array<array-key, mixed>  $values
     */
    public function merge(array $values): static
    {
        return $this->set($values);
    }

    /**
     * Replace all stored properties.
     *
     * @param  array<array-key, mixed>  $properties
     */
    public function replace(array $properties): static
    {
        /** @var array<array-key, mixed> $stored */
        $stored = $this->toStorage('', $properties);

        return $this->write($stored);
    }

    /**
     * Remove one or more properties. Afterwards the default applies again.
     *
     * @param  string|array<array-key, string>  $keys
     */
    public function forget(string|array $keys): static
    {
        $properties = $this->stored();

        Arr::forget($properties, $keys);

        return $this->write($properties);
    }

    /**
     * Remove all stored properties.
     */
    public function clear(): static
    {
        return $this->write([]);
    }

    /**
     * Append values to a list property.
     */
    public function push(string $key, mixed ...$values): static
    {
        $list = $this->get($key, []);

        if (! is_array($list) || ! array_is_list($list)) {
            throw InvalidPropertyValueException::expected($this->model, $key, 'a list', $list);
        }

        return $this->set($key, [...$list, ...array_values($values)]);
    }

    /**
     * Get a property and remove it.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);

        $this->forget($key);

        return $value;
    }

    public function increment(string $key, int|float $amount = 1): static
    {
        $value = $this->get($key, 0);

        if (! is_int($value) && ! is_float($value)) {
            throw InvalidPropertyValueException::expected($this->model, $key, 'a number', $value);
        }

        return $this->set($key, $value + $amount);
    }

    public function decrement(string $key, int|float $amount = 1): static
    {
        return $this->increment($key, -$amount);
    }

    public function toggle(string $key): static
    {
        $value = $this->get($key, false);

        if (! is_bool($value)) {
            throw InvalidPropertyValueException::expected($this->model, $key, 'a boolean', $value);
        }

        return $this->set($key, ! $value);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Stored properties merged into the defaults, without casts.
     *
     * @return array<array-key, mixed>
     */
    protected function merged(): array
    {
        return PropertiesArray::mergeDefaults($this->defaults, $this->stored());
    }

    /**
     * Write the stored form to the model attribute.
     *
     * @param  array<array-key, mixed>  $properties
     */
    protected function write(array $properties): static
    {
        $this->model->setAttribute($this->column, $properties);

        return $this;
    }

    protected function caster(): PropertyCaster
    {
        return $this->caster;
    }

    /*
    |--------------------------------------------------------------------------
    | Interfaces and magic access
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->all();
    }

    /**
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->all(), $options | JSON_THROW_ON_ERROR);
    }

    public function count(): int
    {
        return count($this->all());
    }

    /**
     * @return ArrayIterator<array-key, mixed>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->all());
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null === $offset) {
            throw new InvalidArgumentException('Properties need a key, use push() to append to a list.');
        }

        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->forget((string) $offset);
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function __isset(string $key): bool
    {
        return null !== $this->get($key);
    }

    public function __unset(string $key): void
    {
        $this->forget($key);
    }
}
