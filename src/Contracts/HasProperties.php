<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Contracts;

use TobyMaxham\LaravelProperties\Properties;
use TobyMaxham\LaravelProperties\Enums\PropertiesCast;

/**
 * Optional interface for models with the `HasProperties` trait.
 * Lets other code detect models with properties.
 */
interface HasProperties
{
    public function propertiesColumn(): string;

    public function propertiesCast(): PropertiesCast;

    /**
     * @return array<array-key, mixed>
     */
    public function propertyDefaults(): array;

    /**
     * @return array<array-key, mixed>
     */
    public function propertyCasts(): array;

    public function props(): Properties;

    public function getProperty(string $key, mixed $default = null): mixed;

    public function setProperty(string $key, mixed $value): static;

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function setProperties(array $values): static;

    /**
     * @param  array<array-key, mixed>  $properties
     */
    public function replaceProperties(array $properties): static;

    public function hasProperty(string $key): bool;

    /**
     * @param  string|array<array-key, string>  $keys
     */
    public function forgetProperty(string|array $keys): static;

    /**
     * @return array<array-key, mixed>
     */
    public function getProperties(): array;

    /**
     * @return array<array-key, mixed>
     */
    public function getStoredProperties(): array;

    public function clearProperties(): static;

    public function pushProperty(string $key, mixed ...$values): static;

    public function pullProperty(string $key, mixed $default = null): mixed;

    public function incrementProperty(string $key, int|float $amount = 1): static;

    public function decrementProperty(string $key, int|float $amount = 1): static;

    public function toggleProperty(string $key): static;

    /**
     * @param  string|array<array-key, string>  $keys
     * @return array<array-key, mixed>
     */
    public function onlyProperties(string|array $keys): array;

    /**
     * @param  string|array<array-key, string>  $keys
     * @return array<array-key, mixed>
     */
    public function exceptProperties(string|array $keys): array;

    /**
     * @param  array<array-key, mixed>  $stored
     * @return array<array-key, mixed>
     *
     * @internal
     */
    public function propertiesForSerialization(array $stored): array;
}
