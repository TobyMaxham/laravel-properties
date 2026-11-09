<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\PropertyCasts;

use Throwable;
use DateTimeInterface;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\Date;
use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\Contracts\PropertyCast;
use TobyMaxham\LaravelProperties\Exceptions\InvalidPropertyValueException;

/**
 * `date`, `datetime`, `immutable_date` and `immutable_datetime`, optionally with a format: `datetime:d.m.Y H:i`.
 *
 * Dates are stored as `Y-m-d`, date times as `Y-m-d H:i:s` (or in the given format), like Laravel stores
 * dates in the database. The timezone is not converted. The class of the result follows `Date::use()`.
 */
final class DateCast implements PropertyCast
{
    public const array TYPES = ['date', 'datetime', 'immutable_date', 'immutable_datetime'];

    public function __construct(private readonly string $type, private readonly ?string $format = null)
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown date property cast [%s].', $type));
        }
    }

    public function get(mixed $value, string $key, Model $model): ?CarbonInterface
    {
        if (null === $value) {
            return null;
        }

        $date = $this->parse($value, $key, $model);

        if (str_ends_with($this->type, 'date')) {
            $date = $date->startOfDay();
        }

        return str_starts_with($this->type, 'immutable') ? $date->toImmutable() : $date;
    }

    public function set(mixed $value, string $key, Model $model): ?string
    {
        if (null === $value) {
            return null;
        }

        return $this->parse($value, $key, $model)->format($this->storageFormat());
    }

    public function storageFormat(): string
    {
        return $this->format ?? (str_ends_with($this->type, 'date') ? 'Y-m-d' : 'Y-m-d H:i:s');
    }

    private function parse(mixed $value, string $key, Model $model): CarbonInterface
    {
        try {
            $date = match (true) {
                $value instanceof DateTimeInterface => Date::instance($value),
                is_int($value), is_float($value)    => Date::createFromTimestamp($value),
                is_string($value)                   => $this->parseString($value),
                default                             => null,
            };
        } catch (Throwable) {
            $date = null;
        }

        return $date ?? throw InvalidPropertyValueException::expected($model, $key, 'a date', $value);
    }

    private function parseString(string $value): ?CarbonInterface
    {
        // Values in another format (e.g. stored before the format was set) fall back to parsing.
        if (null !== $this->format) {
            try {
                return Date::createFromFormat($this->format, $value) ?: throw new InvalidArgumentException;
            } catch (Throwable) {
            }
        }

        if (1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Date::createFromFormat('Y-m-d', $value)?->startOfDay();
        }

        return '' === trim($value) ? null : Date::parse($value);
    }
}
