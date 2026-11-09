<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\PropertyCasts;

use BackedEnum;
use ValueError;
use ReflectionEnum;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\Contracts\PropertyCast;
use TobyMaxham\LaravelProperties\Exceptions\InvalidPropertyValueException;

/**
 * Backed enums. Use the enum class as cast: `'status' => Status::class`.
 *
 * Strict (default): an unknown value throws an exception when reading or writing.
 * Lenient: `EnumCast::lenient(Status::class)` returns `null` for unknown values instead.
 */
final class EnumCast implements PropertyCast
{
    private readonly bool $intBacked;

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public function __construct(private readonly string $enum, private readonly bool $strict = true)
    {
        if (! is_subclass_of($enum, BackedEnum::class)) {
            throw new InvalidArgumentException(sprintf('The property cast [%s] must be a backed enum.', $enum));
        }

        $this->intBacked = 'int' === (string) (new ReflectionEnum($enum))->getBackingType();
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public static function lenient(string $enum): self
    {
        return new self($enum, strict: false);
    }

    public function get(mixed $value, string $key, Model $model): ?BackedEnum
    {
        return $this->resolve($value, $key, $model);
    }

    public function set(mixed $value, string $key, Model $model): int|string|null
    {
        return $this->resolve($value, $key, $model)?->value;
    }

    private function resolve(mixed $value, string $key, Model $model): ?BackedEnum
    {
        if (null === $value || $value instanceof $this->enum) {
            return $value;
        }

        if ($this->intBacked && is_string($value) && false !== filter_var($value, FILTER_VALIDATE_INT)) {
            $value = (int) $value;
        }

        try {
            $enum = $this->intBacked ? (is_int($value) ? $this->enum::from($value) : null) : (is_string($value) ? $this->enum::from($value) : null);
        } catch (ValueError) {
            $enum = null;
        }

        if (null === $enum && $this->strict) {
            throw InvalidPropertyValueException::invalidValue($model, $key, sprintf(
                '%s is not a valid value of [%s]',
                is_scalar($value) ? var_export($value, true) : '['.get_debug_type($value).']', $this->enum,
            ));
        }

        return $enum;
    }
}
