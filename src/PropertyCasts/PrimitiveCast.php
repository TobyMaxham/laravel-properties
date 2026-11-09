<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\PropertyCasts;

use BackedEnum;
use Stringable;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;
use TobyMaxham\LaravelProperties\Contracts\PropertyCast;
use TobyMaxham\LaravelProperties\Exceptions\InvalidPropertyValueException;

/**
 * `int`, `float`, `decimal:<digits>`, `bool`, `string`, `array` and `collection`.
 *
 * Writing is strict: a value that does not fit throws an exception (e.g. `'abc'` for `int`).
 * Reading converts like Laravel's attribute casts and never throws, so old data stays readable.
 */
final class PrimitiveCast implements PropertyCast
{
    public const array TYPES = [
        'int'        => 'int',
        'integer'    => 'int',
        'float'      => 'float',
        'double'     => 'float',
        'real'       => 'float',
        'decimal'    => 'decimal',
        'bool'       => 'bool',
        'boolean'    => 'bool',
        'string'     => 'string',
        'array'      => 'array',
        'json'       => 'array',
        'collection' => 'collection',
    ];

    private readonly string $type;

    public function __construct(string $type, private readonly ?int $digits = null)
    {
        $this->type = self::TYPES[$type] ?? throw new InvalidArgumentException(sprintf('Unknown primitive property cast [%s].', $type));

        if ('decimal' === $this->type && (null === $digits || $digits < 0)) {
            throw new InvalidArgumentException('The decimal property cast needs the number of digits, e.g. [decimal:2].');
        }
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, ['int', 'float', 'decimal'], true);
    }

    public function get(mixed $value, string $key, Model $model): mixed
    {
        if (null === $value) {
            return null;
        }

        return match ($this->type) {
            'int'        => is_scalar($value) ? (int) $value : $value,
            'float'      => is_scalar($value) ? (float) $value : $value,
            'decimal'    => is_numeric($value) ? $this->decimal($value) : $value,
            'bool'       => is_scalar($value) ? $this->bool($value) ?? (bool) $value : $value,
            'string'     => is_scalar($value) || $value instanceof Stringable ? (string) $value : $value,
            'array'      => $this->array($value) ?? $value,
            'collection' => new Collection($this->array($value) ?? [$value]),
        };
    }

    public function set(mixed $value, string $key, Model $model): mixed
    {
        if (null === $value) {
            return null;
        }

        $result = match ($this->type) {
            'int'                 => $this->int($value),
            'float'               => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? (float) $value : null,
            'decimal'             => is_numeric($value) ? $this->decimal($value) : null,
            'bool'                => $this->bool($value),
            'string'              => $this->string($value),
            'array', 'collection' => $this->array($value),
        };

        return $result ?? throw InvalidPropertyValueException::expected($model, $key, $this->description(), $value);
    }

    private function int(mixed $value): ?int
    {
        return match (true) {
            is_int($value)                                                         => $value,
            is_float($value) && floor($value) === $value                           => (int) $value,
            is_string($value) && false !== filter_var($value, FILTER_VALIDATE_INT) => (int) $value,
            default                                                                => null,
        };
    }

    private function bool(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value)                  => $value,
            is_int($value), is_float($value) => 0 != $value,
            is_string($value)                => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            default                          => null,
        };
    }

    private function string(mixed $value): ?string
    {
        return match (true) {
            is_string($value)                => $value,
            is_int($value), is_float($value) => (string) $value,
            $value instanceof BackedEnum     => (string) $value->value,
            $value instanceof Stringable     => (string) $value,
            default                          => null,
        };
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function array(mixed $value): ?array
    {
        return match (true) {
            is_array($value)            => $value,
            $value instanceof Arrayable => $value->toArray(),
            default                     => null,
        };
    }

    private function decimal(int|float|string $value): string
    {
        return number_format((float) $value, (int) $this->digits, '.', '');
    }

    private function description(): string
    {
        return match ($this->type) {
            'int'                 => 'an integer',
            'float', 'decimal'    => 'a number',
            'bool'                => 'a boolean',
            'string'              => 'a string',
            'array', 'collection' => 'an array',
        };
    }
}
