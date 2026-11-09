<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\PropertyCasts;

use JsonException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Encryption\DecryptException;
use TobyMaxham\LaravelProperties\Contracts\PropertyCast;
use TobyMaxham\LaravelProperties\Support\PropertiesArray;
use TobyMaxham\LaravelProperties\Exceptions\InvalidPropertyValueException;

/**
 * Encrypts a single property: `'api_token' => 'encrypted'`, optionally with a cast for the
 * decrypted value: `'encrypted:int'`, `'encrypted:datetime'`, `'encrypted:'.Status::class`.
 *
 * The value is stored as JSON before it is encrypted, so the type survives (`int`, `array`, ...).
 * Uses the encrypter of the model (`Model::encryptUsing()`, `APP_PREVIOUS_KEYS` for key rotation).
 * Encrypted properties can not be queried and are hidden in `toArray()` / JSON.
 */
final class EncryptedCast implements PropertyCast
{
    public function __construct(private readonly ?PropertyCast $inner = null) {}

    public function inner(): ?PropertyCast
    {
        return $this->inner;
    }

    public function get(mixed $value, string $key, Model $model): mixed
    {
        if (null === $value) {
            return null;
        }

        $decrypted = $this->decrypt($value, $key, $model);

        return null === $this->inner ? $decrypted : $this->inner->get($decrypted, $key, $model);
    }

    public function set(mixed $value, string $key, Model $model): ?string
    {
        $value = null === $this->inner ? $value : $this->inner->set($value, $key, $model);

        if (null === $value) {
            return null;
        }

        return $model::currentEncrypter()->encrypt(json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), false);
    }

    /**
     * Whether the stored (encrypted) value already contains `$value`. Then the old
     * encryption can be kept and the model does not become dirty.
     */
    public function contains(mixed $stored, mixed $value, string $key, Model $model): bool
    {
        if (null === $stored || null === $value) {
            return $stored === $value;
        }

        try {
            $current = $this->decrypt($stored, $key, $model);
            $new = json_decode(json_encode(
                null === $this->inner ? $value : $this->inner->set($value, $key, $model),
                JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            ), true, 512, JSON_THROW_ON_ERROR);
        } catch (InvalidPropertyValueException|JsonException) {
            return false;
        }

        return is_array($current) && is_array($new) ? PropertiesArray::equals($current, $new) : $current === $new;
    }

    private function decrypt(mixed $value, string $key, Model $model): mixed
    {
        if (! is_string($value)) {
            throw InvalidPropertyValueException::invalidValue($model, $key, 'the value is not encrypted');
        }

        try {
            return json_decode($model::currentEncrypter()->decrypt($value, false), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw InvalidPropertyValueException::invalidValue($model, $key, 'the value can not be decrypted');
        }
    }
}
