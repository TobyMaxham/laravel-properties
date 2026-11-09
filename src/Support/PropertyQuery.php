<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Support;

use Closure;
use BackedEnum;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use TobyMaxham\LaravelProperties\Properties;
use TobyMaxham\LaravelProperties\PropertyCasts\PrimitiveCast;

/**
 * Builds the SQL for the property scopes of `HasProperties`.
 *
 * - Values are converted with the property casts (enum → value, Carbon → string, ...).
 * - If the default of a property matches the condition, rows without the property match too.
 * - Numbers are compared and sorted as numbers on every database.
 * - Encrypted properties throw a `CannotQueryEncryptedPropertyException`.
 *
 * @internal
 */
final class PropertyQuery
{
    private const array COMPARISONS = ['=', '!=', '<>', '<', '>', '<=', '>='];

    private readonly Properties $props;

    /**
     * @param  Builder<Model>  $query
     */
    public function __construct(private readonly Builder $query, Properties $props)
    {
        $this->props = $props;
    }

    public function where(string $key, string $operator, mixed $value, string $boolean = 'and'): void
    {
        $operator = strtolower($operator);
        $this->ensureOperator($operator);
        $this->props->ensureQueryable($key);

        $value = $this->toStorage($key, $value);

        if (null === $value && in_array($operator, ['=', '!=', '<>'], true)) {
            $this->whereNull($key, $boolean, '=' !== $operator);

            return;
        }

        $this->withDefault(
            $key, $boolean,
            fn (Builder $query, string $boolean) => $this->compare($query, $key, $operator, $value, $boolean),
            fn (mixed $default) => $this->matches($default, $operator, $value),
        );
    }

    /**
     * @param  array<array-key, mixed>  $conditions
     */
    public function whereAll(array $conditions, string $boolean = 'and'): void
    {
        $this->query->where(function (Builder $query) use ($conditions) {
            foreach ($conditions as $key => $value) {
                (new self($query, $this->props))->where((string) $key, '=', $value);
            }
        }, boolean: $boolean);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function whereIn(string $key, array $values, string $boolean = 'and', bool $not = false): void
    {
        $this->props->ensureQueryable($key);

        $values = array_values(array_map(fn (mixed $value) => $this->toStorage($key, $value), $values));

        $this->withDefault(
            $key, $boolean,
            function (Builder $query, string $boolean) use ($key, $values, $not) {
                if ([] === $values) {
                    $query->whereIn($this->path($key), [], $boolean, $not);

                    return;
                }

                if ([] === array_filter($values, fn (mixed $value) => ! is_string($value))) {
                    $this->guarded($query, $key, $boolean, fn (Builder $query, string $boolean) => $query->whereIn($this->path($key), $values, $boolean, $not));

                    return;
                }

                // Numbers, booleans and null need their own comparison.
                $query->where(function (Builder $query) use ($key, $values, $not) {
                    foreach ($values as $value) {
                        null === $value
                            ? (new self($query, $this->props))->whereNull($key, $not ? 'and' : 'or', $not)
                            : $this->compare($query, $key, $not ? '!=' : '=', $value, $not ? 'and' : 'or');
                    }
                }, boolean: $boolean);
            },
            fn (mixed $default) => $not xor in_array($default, $values, false),
        );
    }

    public function whereNull(string $key, string $boolean = 'and', bool $not = false): void
    {
        $this->props->ensureQueryable($key);

        $path = $this->path($key);
        $hasDefault = $this->hasDefault($key) && null !== $this->defaultValue($key);

        if (! $hasDefault) {
            $this->query->whereNull($path, $boolean, $not);

            return;
        }

        // A missing property has a default, so it is not null.
        $this->query->where(fn (Builder $query) => $not
            ? $query->whereNotNull($path)->orWhereJsonDoesntContainKey($path)
            : $query->whereJsonContainsKey($path)->whereNull($path),
            boolean: $boolean);
    }

    /**
     * @param  array{0: mixed, 1: mixed}|array<array-key, mixed>  $range
     */
    public function whereBetween(string $key, array $range, string $boolean = 'and', bool $not = false): void
    {
        $this->props->ensureQueryable($key);

        if (2 !== count($range)) {
            throw new InvalidArgumentException('The range of a property must contain exactly two values.');
        }

        [$min, $max] = array_map(fn (mixed $value) => $this->toStorage($key, $value), array_values($range));

        $this->withDefault(
            $key, $boolean,
            fn (Builder $query, string $boolean) => $this->guarded($query, $key, $boolean, fn (Builder $query, string $boolean) => $this->isNumber($min) && $this->isNumber($max)
                ? $query->whereRaw(new SqlExpression(sprintf('%s%s between %s and %s', $this->numeric($query, $key), $not ? ' not' : '', $this->placeholder($query), $this->placeholder($query))), [$min, $max], $boolean)
                : $query->whereBetween($this->path($key), [$min, $max], $boolean, $not)),
            fn (mixed $default) => $not xor ($this->matches($default, '>=', $min) && $this->matches($default, '<=', $max)),
        );
    }

    public function whereHas(string $key, string $boolean = 'and', bool $not = false): void
    {
        $this->props->ensureQueryable($key, keyOnly: true);

        // Like `hasProperty()`: a property with a default always exists.
        if ($this->hasDefault($key)) {
            $this->query->whereRaw($not ? '1 = 0' : '1 = 1', [], $boolean);

            return;
        }

        $not
            ? $this->query->whereJsonDoesntContainKey($this->path($key), $boolean)
            : $this->query->whereJsonContainsKey($this->path($key), $boolean);
    }

    public function whereContains(string $key, mixed $value, string $boolean = 'and', bool $not = false): void
    {
        $this->props->ensureQueryable($key);

        $values = array_map($this->scalar(...), is_array($value) ? array_values($value) : [$value]);

        $condition = function (Builder $query, string $boolean) use ($key, $values, $not) {
            // One condition per value, SQLite only supports scalar values. For "doesn't contain" the list
            // must exist, missing lists are handled below (the databases treat them differently).
            $query->where(function (Builder $query) use ($key, $values, $not) {
                if ($not) {
                    $query->whereJsonContainsKey($this->path($key));
                }

                $query->where(function (Builder $query) use ($key, $values, $not) {
                    foreach ($values as $value) {
                        $query->whereJsonContains($this->path($key), $value, $not ? 'or' : 'and', $not);
                    }
                });
            }, boolean: $boolean);
        };

        // A missing list without default is empty: it contains nothing.
        $this->withDefault(
            $key, $boolean, $condition,
            fn (mixed $default) => is_array($default) && ($not xor $this->containsAll($default, $values)),
            missingMatches: $not,
        );
    }

    public function whereLength(string $key, string $operator, int $value, string $boolean = 'and'): void
    {
        $this->ensureOperator($operator);
        $this->props->ensureQueryable($key);

        $this->withDefault(
            $key, $boolean,
            fn (Builder $query, string $boolean) => $query->whereJsonLength($this->path($key), $operator, $value, $boolean),
            fn (mixed $default) => is_array($default) && $this->matches(count($default), $operator, $value),
        );
    }

    public function orderBy(string $key, string $direction = 'asc'): void
    {
        $this->props->ensureQueryable($key);

        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('The order direction must be "asc" or "desc".');
        }

        $cast = $this->props->castFor($key);
        $expression = $cast instanceof PrimitiveCast && $cast->isNumeric()
            ? $this->numeric($this->query, $key)
            : $this->query->getQuery()->getGrammar()->wrap($this->path($key));

        $default = $this->hasDefault($key) ? $this->defaultValue($key) : null;

        if (is_string($default) || $this->isNumber($default)) {
            $expression = sprintf('coalesce(%s, %s)', $expression, is_string($default) ? '?' : $this->placeholder($this->query));
            $this->query->getQuery()->addBinding($default, 'order');
        }

        $this->query->orderBy(new SqlExpression($expression), $direction);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Model>  $query
     */
    private function compare(Builder $query, string $key, string $operator, mixed $value, string $boolean): void
    {
        $this->guarded($query, $key, $boolean, fn (Builder $query, string $boolean) => $this->isNumber($value)
            ? $query->whereRaw(new SqlExpression($this->numeric($query, $key).' '.$operator.' '.$this->placeholder($query)), [$value], $boolean)
            : $query->where($this->path($key), $operator, $value, $boolean));
    }

    /**
     * MySQL / MariaDB turn a JSON `null` into the string `'null'`, so `!= 'light'` or `< 10` would match it.
     * Excludes JSON `null` there, like the other databases do.
     *
     * @param  Builder<Model>  $query
     * @param  Closure(Builder<Model>, string): mixed  $condition
     */
    private function guarded(Builder $query, string $key, string $boolean, Closure $condition): void
    {
        if (! in_array($this->driver($query), ['mysql', 'mariadb'], true)) {
            $condition($query, $boolean);

            return;
        }

        $query->where(function (Builder $query) use ($condition, $key) {
            $condition($query, 'and');
            $query->whereNotNull($this->path($key));
        }, boolean: $boolean);
    }

    /**
     * Adds `OR property is missing` if the default of the property matches the condition.
     *
     * @param  Closure(Builder<Model>, string): mixed  $condition
     * @param  Closure(mixed): bool  $defaultMatches
     * @param  bool  $missingMatches  whether a missing property without default matches
     */
    private function withDefault(string $key, string $boolean, Closure $condition, Closure $defaultMatches, bool $missingMatches = false): void
    {
        $matches = $this->hasDefault($key) ? $defaultMatches($this->defaultValue($key)) : $missingMatches;

        if (! $matches) {
            $condition($this->query, $boolean);

            return;
        }

        $this->query->where(function (Builder $query) use ($condition, $key) {
            $condition($query, 'and');
            $query->orWhereJsonDoesntContainKey($this->path($key));
        }, boolean: $boolean);
    }

    private function matches(mixed $default, string $operator, mixed $value): bool
    {
        if (null === $default || is_array($default)) {
            return false;
        }

        return match ($operator) {
            '='        => $default == $value,
            '!=', '<>' => $default != $value,
            '<'        => $default < $value,
            '>'        => $default > $value,
            '<='       => $default <= $value,
            '>='       => $default >= $value,
            default    => false, // like, ilike, ...: only the stored values are checked
        };
    }

    /**
     * SQL expression of the property as number. Other values become `null` (PostgreSQL) or `0` (MySQL).
     *
     * @param  Builder<Model>  $query
     */
    private function numeric(Builder $query, string $key): string
    {
        $expression = $query->getQuery()->getGrammar()->wrap($this->path($key));

        return match ($this->driver($query)) {
            'mysql', 'mariadb' => "cast({$expression} as double)",
            'pgsql'            => "(case when ({$expression}) ~ '^-?[0-9]+(\\.[0-9]+)?([eE][-+]?[0-9]+)?$' then ({$expression})::numeric end)",
            'sqlsrv'           => "try_cast({$expression} as float)",
            default            => $expression, // SQLite returns numbers from json_extract()
        };
    }

    /**
     * Placeholder for a number. PDO binds floats as strings, SQLite would compare them as text.
     *
     * @param  Builder<Model>  $query
     */
    private function placeholder(Builder $query): string
    {
        return 'sqlite' === $this->driver($query) ? 'cast(? as numeric)' : '?';
    }

    /**
     * `ui.size` → `items.properties->ui->size`, `tags.0` → `items.properties->tags[0]`.
     */
    private function path(string $key): string
    {
        $path = $this->query->qualifyColumn($this->props->column());

        foreach (explode('.', $key) as $index => $segment) {
            $path .= $index > 0 && ctype_digit($segment) ? '['.$segment.']' : '->'.$segment;
        }

        return $path;
    }

    private function toStorage(string $key, mixed $value): mixed
    {
        return $this->scalar($this->props->toStorage($key, $value));
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private function hasDefault(string $key): bool
    {
        return Arr::has($this->props->defaults(), $key);
    }

    private function defaultValue(string $key): mixed
    {
        return $this->toStorage($key, Arr::get($this->props->defaults(), $key));
    }

    /**
     * @param  array<array-key, mixed>  $list
     * @param  array<array-key, mixed>  $values
     */
    private function containsAll(array $list, array $values): bool
    {
        foreach ($values as $value) {
            if (! in_array($value, $list, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function driver(Builder $query): string
    {
        $connection = $query->getConnection();

        return $connection instanceof Connection ? $connection->getDriverName() : '';
    }

    private function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private function ensureOperator(string $operator): void
    {
        $operators = [...self::COMPARISONS, ...$this->query->getQuery()->operators, ...$this->query->getQuery()->getGrammar()->getOperators()];

        if (! in_array(strtolower($operator), $operators, true)) {
            throw new InvalidArgumentException(sprintf('Invalid operator [%s] for a property query.', $operator));
        }
    }
}
