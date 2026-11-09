<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Casts;

use ValueError;
use JsonException;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Contracts\Encryption\DecryptException;
use TobyMaxham\LaravelProperties\Enums\PropertiesCast;
use TobyMaxham\LaravelProperties\Support\PropertiesArray;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use TobyMaxham\LaravelProperties\Exceptions\InvalidAttributeTypeException;

/**
 * Cast for the properties column. Added automatically by the `HasProperties` trait.
 *
 * Can also be used on its own: `'settings' => AsProperties::using(PropertiesCast::Collection)`.
 *
 * @implements CastsAttributes<array<array-key, mixed>|ArrayObject<array-key, mixed>|Collection<array-key, mixed>, mixed>
 */
class AsProperties implements CastsAttributes, ComparesCastableAttributes, SerializesCastableAttributes
{
    public readonly PropertiesCast $cast;

    public function __construct(PropertiesCast|string $cast = PropertiesCast::Array)
    {
        try {
            $this->cast = is_string($cast) ? PropertiesCast::from($cast) : $cast;
        } catch (ValueError) {
            throw new InvalidArgumentException(sprintf(
                'Invalid properties cast [%s]. Allowed: %s.',
                $cast, implode(', ', array_column(PropertiesCast::cases(), 'value')),
            ));
        }
    }

    /**
     * The cast string for the `$casts` array of a model.
     */
    public static function using(PropertiesCast $cast = PropertiesCast::Array): string
    {
        return static::class.':'.$cast->value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<array-key, mixed>|ArrayObject<array-key, mixed>|Collection<array-key, mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array|ArrayObject|Collection
    {
        return $this->cast->wrap($this->decode($model, $key, $value));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (null === $value) {
            return [$key => null];
        }

        $properties = PropertiesArray::normalize($value)
            ?? throw InvalidAttributeTypeException::notAnArray($model, $key, $value);

        $json = PropertiesArray::toJson($properties);

        return [$key => $this->cast->isEncrypted() ? $model::currentEncrypter()->encrypt($json, false) : $json];
    }

    /**
     * Decides whether the column is dirty. Compares the decoded values, so a different
     * JSON format of the database or a new encryption of the same data is not a change.
     */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        if (null === $firstValue || null === $secondValue) {
            return $firstValue === $secondValue;
        }

        try {
            return PropertiesArray::equals(
                $this->decode($model, $key, $firstValue),
                $this->decode($model, $key, $secondValue),
            );
        } catch (InvalidAttributeTypeException) {
            return false;
        }
    }

    /**
     * Output for `toArray()` / JSON.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<array-key, mixed>
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): array
    {
        $properties = PropertiesArray::normalize($value) ?? [];

        if (method_exists($model, 'propertiesForSerialization') && method_exists($model, 'propertiesColumn')
            && $key === $model->propertiesColumn()) {
            return $model->propertiesForSerialization($properties);
        }

        return $properties;
    }

    /**
     * Decode the raw value of the column into an array.
     *
     * @return array<array-key, mixed>
     */
    public function decode(Model $model, string $key, mixed $value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (! is_string($value)) {
            return PropertiesArray::normalize($value)
                ?? throw InvalidAttributeTypeException::notAnArray($model, $key, $value);
        }

        try {
            if ($this->cast->isEncrypted()) {
                $value = $model->fromEncryptedString($value);
            }

            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            throw InvalidAttributeTypeException::invalidJson($model, $key, $exception->getMessage());
        }

        if (! is_array($decoded)) {
            throw InvalidAttributeTypeException::notAnArray($model, $key, $decoded);
        }

        return $decoded;
    }
}
