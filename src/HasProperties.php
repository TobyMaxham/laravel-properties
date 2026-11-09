<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use TobyMaxham\LaravelProperties\Casts\AsProperties;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use TobyMaxham\LaravelProperties\Enums\PropertiesCast;
use TobyMaxham\LaravelProperties\Support\PropertiesArray;
use TobyMaxham\LaravelProperties\Concerns\QueriesProperties;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;

/**
 * Store flexible properties in a JSON column of an Eloquent model.
 *
 * Configuration (each one optional, a method wins over a constant, a constant over a property):
 *
 * | What                      | Method                         | Constant                      | Property                      | Default        |
 * |---------------------------|--------------------------------|-------------------------------|-------------------------------|----------------|
 * | Column                    | `propertiesColumn()`           | `PROPERTIES_COLUMN`           | `$propertiesColumn`           | `'properties'` |
 * | Type of the attribute     | `propertiesCast()`             | `PROPERTIES_CAST`             | `$propertiesCast`             | `Array`        |
 * | Defaults                  | `propertyDefaults()`           | `PROPERTY_DEFAULTS`           | `$propertyDefaults`           | `[]`           |
 * | Casts per property        | `propertyCasts()`              | `PROPERTY_CASTS`              | `$propertyCasts`              | `[]`           |
 * | Defaults in `toArray()`   | `serializePropertyDefaults()`  | `SERIALIZE_PROPERTY_DEFAULTS` | `$serializePropertyDefaults`  | `false`        |
 *
 * @phpstan-require-extends Model
 */
trait HasProperties
{
    use QueriesProperties;

    /**
     * Set with `withPropertyDefaults()` / `withoutPropertyDefaults()` for a single instance.
     */
    protected ?bool $serializePropertyDefaultsOverride = null;

    /**
     * Adds the `AsProperties` cast for the column. Called by Eloquent for every new instance.
     */
    protected function initializeHasProperties(): void
    {
        $column = $this->propertiesColumn();
        $existing = $this->getCasts()[$column] ?? null;

        // Keep a custom cast of the developer, the trait works with its result.
        if (null !== $existing && null === $this->propertiesCastFromModelCast($existing)) {
            return;
        }

        $this->mergeCasts([$column => AsProperties::using($this->propertiesCast())]);
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    public function propertiesColumn(): string
    {
        $column = $this->propertiesConfig('PROPERTIES_COLUMN', 'propertiesColumn')
            ?? $this->propertiesConfig(null, 'propertiesField') // 1.x
            ?? 'properties';

        if (! is_string($column) || '' === $column) {
            throw new InvalidArgumentException(sprintf('The properties column of [%s] must be a non-empty string.', static::class));
        }

        return $column;
    }

    public function propertiesCast(): PropertiesCast
    {
        $cast = $this->propertiesConfig('PROPERTIES_CAST', 'propertiesCast');

        if ($cast instanceof PropertiesCast) {
            return $cast;
        }

        if (is_string($cast)) {
            return PropertiesCast::tryFrom($cast) ?? throw new InvalidArgumentException(sprintf(
                'Invalid properties cast [%s] on [%s]. Allowed: %s.',
                $cast, static::class, implode(', ', array_column(PropertiesCast::cases(), 'value')),
            ));
        }

        // Without a configuration, keep the type of an existing Laravel cast (upgrade from `'properties' => 'array'` etc.).
        $existing = $this->getCasts()[$this->propertiesColumn()] ?? null;

        return (null !== $existing ? $this->propertiesCastFromModelCast($existing) : null) ?? PropertiesCast::Array;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function propertyDefaults(): array
    {
        $defaults = $this->propertiesConfig('PROPERTY_DEFAULTS', 'propertyDefaults') ?? [];

        if (! is_array($defaults)) {
            throw new InvalidArgumentException(sprintf('The property defaults of [%s] must be an array.', static::class));
        }

        return $defaults;
    }

    /**
     * Casts per property, keys may use "dot" notation:
     * `['newsletter' => 'bool', 'birthday' => 'date', 'status' => Status::class, 'price' => 'decimal:2']`.
     *
     * @return array<array-key, mixed>
     */
    public function propertyCasts(): array
    {
        $casts = $this->propertiesConfig('PROPERTY_CASTS', 'propertyCasts') ?? [];

        if (! is_array($casts)) {
            throw new InvalidArgumentException(sprintf('The property casts of [%s] must be an array.', static::class));
        }

        return $casts;
    }

    public function serializePropertyDefaults(): bool
    {
        return (bool) ($this->propertiesConfig('SERIALIZE_PROPERTY_DEFAULTS', 'serializePropertyDefaults') ?? false);
    }

    /**
     * Include the defaults in `toArray()` / JSON of this instance.
     */
    public function withPropertyDefaults(bool $withDefaults = true): static
    {
        $this->serializePropertyDefaultsOverride = $withDefaults;

        return $this;
    }

    /**
     * Only include the stored properties in `toArray()` / JSON of this instance.
     */
    public function withoutPropertyDefaults(): static
    {
        return $this->withPropertyDefaults(false);
    }

    /**
     * @param  array<array-key, mixed>  $stored
     * @return array<array-key, mixed>
     *
     * @internal Used by the `AsProperties` cast.
     */
    public function propertiesForSerialization(array $stored): array
    {
        $props = $this->props();

        if ($this->serializePropertyDefaultsOverride ?? $this->serializePropertyDefaults()) {
            /** @var array<array-key, mixed> $defaults */
            $defaults = $props->toStorage('', $this->propertyDefaults());

            $stored = PropertiesArray::mergeDefaults($defaults, $stored);
        }

        // Encrypted properties are hidden, like `$hidden` attributes.
        Arr::forget($stored, $props->encrypted());

        return $stored;
    }

    /*
    |--------------------------------------------------------------------------
    | Reading (all methods are shortcuts for `props()`)
    |--------------------------------------------------------------------------
    */

    /**
     * Fluent access to the properties: `$model->props()->get('theme')`, `$model->props()->theme`, ...
     */
    public function props(): Properties
    {
        return new Properties(
            $this,
            $this->propertiesColumn(),
            $this->propertyDefaults(),
            $this->propertyCasts(),
            $this->propertiesCast()->isEncrypted(),
        );
    }

    /**
     * Get a property with "dot" notation. Falls back to the defaults of the model, then to `$default`.
     */
    public function getProperty(string $key, mixed $default = null): mixed
    {
        return $this->props()->get($key, $default);
    }

    /**
     * Whether the property is stored or has a default.
     */
    public function hasProperty(string $key): bool
    {
        return $this->props()->has($key);
    }

    /**
     * All properties: the stored values merged into the defaults.
     *
     * @return array<array-key, mixed>
     */
    public function getProperties(): array
    {
        return $this->props()->all();
    }

    /**
     * Only the stored properties, without defaults.
     *
     * @return array<array-key, mixed>
     */
    public function getStoredProperties(): array
    {
        return $this->props()->stored();
    }

    /**
     * @param  string|array<array-key, string>  $keys
     * @return array<array-key, mixed>
     */
    public function onlyProperties(string|array $keys): array
    {
        return $this->props()->only($keys);
    }

    /**
     * @param  string|array<array-key, string>  $keys
     * @return array<array-key, mixed>
     */
    public function exceptProperties(string|array $keys): array
    {
        return $this->props()->except($keys);
    }

    /**
     * Get a property and remove it.
     */
    public function pullProperty(string $key, mixed $default = null): mixed
    {
        return $this->props()->pull($key, $default);
    }

    /*
    |--------------------------------------------------------------------------
    | Writing (the model is not saved)
    |--------------------------------------------------------------------------
    */

    /**
     * Set a property with "dot" notation.
     */
    public function setProperty(string $key, mixed $value): static
    {
        $this->props()->set($key, $value);

        return $this;
    }

    /**
     * Set several properties, keys may use "dot" notation. Other properties are kept.
     *
     * @param  array<array-key, mixed>  $values
     */
    public function setProperties(array $values): static
    {
        $this->props()->set($values);

        return $this;
    }

    /**
     * Replace all stored properties.
     *
     * @param  array<array-key, mixed>  $properties
     */
    public function replaceProperties(array $properties): static
    {
        $this->props()->replace($properties);

        return $this;
    }

    /**
     * Remove one or more properties. Afterwards the default applies again.
     *
     * @param  string|array<array-key, string>  $keys
     */
    public function forgetProperty(string|array $keys): static
    {
        $this->props()->forget($keys);

        return $this;
    }

    /**
     * Remove all stored properties.
     */
    public function clearProperties(): static
    {
        $this->props()->clear();

        return $this;
    }

    /**
     * Remove all stored properties and return the old ones.
     *
     * @return array<array-key, mixed>
     *
     * @deprecated Use `clearProperties()`. Will be removed in 3.0.
     */
    public function emptyProperties(): array
    {
        $properties = $this->getStoredProperties();

        $this->clearProperties();

        return $properties;
    }

    /**
     * Append values to a list property.
     */
    public function pushProperty(string $key, mixed ...$values): static
    {
        $this->props()->push($key, ...$values);

        return $this;
    }

    public function incrementProperty(string $key, int|float $amount = 1): static
    {
        $this->props()->increment($key, $amount);

        return $this;
    }

    public function decrementProperty(string $key, int|float $amount = 1): static
    {
        $this->props()->decrement($key, $amount);

        return $this;
    }

    public function toggleProperty(string $key): static
    {
        $this->props()->toggle($key);

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Read a constant or property of the model, without declaring it in the trait
     * (a property declared in a trait can not be redeclared with another value).
     */
    private function propertiesConfig(?string $constant, string $property): mixed
    {
        if (null !== $constant && defined(static::class.'::'.$constant)) {
            return constant(static::class.'::'.$constant);
        }

        return property_exists($this, $property) ? $this->{$property} : null;
    }

    /**
     * Map a Laravel cast of the column to the matching properties cast. `null` for unknown casts.
     */
    private function propertiesCastFromModelCast(string $cast): ?PropertiesCast
    {
        return match (true) {
            Str::startsWith($cast, AsProperties::class)                                            => (new AsProperties(str_contains($cast, ':') ? Str::after($cast, ':') : PropertiesCast::Array))->cast,
            Str::startsWith($cast, AsEncryptedArrayObject::class), 'encrypted:object' === $cast    => PropertiesCast::EncryptedObject,
            Str::startsWith($cast, AsEncryptedCollection::class), 'encrypted:collection' === $cast => PropertiesCast::EncryptedCollection,
            Str::startsWith($cast, AsArrayObject::class), 'object' === $cast                       => PropertiesCast::Object,
            Str::startsWith($cast, AsCollection::class), 'collection' === $cast                    => PropertiesCast::Collection,
            in_array($cast, ['encrypted:array', 'encrypted:json'], true)                           => PropertiesCast::EncryptedArray,
            in_array($cast, ['array', 'json', 'json:unicode'], true)                               => PropertiesCast::Array,
            default                                                                                => null,
        };
    }
}
