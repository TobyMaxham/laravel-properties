<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use TobyMaxham\LaravelProperties\Support\PropertyQuery;

/**
 * Query scopes for properties, part of `HasProperties`.
 *
 * ```php
 * User::whereProperty('theme', 'dark')->get();
 * User::whereProperty('logins', '>', 10)->orderByProperty('logins', 'desc')->get();
 * User::whereProperties(['status' => Status::Active, 'newsletter' => true])->get();
 * ```
 *
 * @phpstan-require-extends Model
 */
trait QueriesProperties
{
    /**
     * `whereProperty('theme', 'dark')` or `whereProperty('logins', '>', 10)`.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereProperty(Builder $query, string $key, mixed $operator = null, mixed $value = null, string $boolean = 'and'): Builder
    {
        if (3 === func_num_args()) {
            [$operator, $value] = ['=', $operator];
        }

        if (! is_string($operator)) {
            throw new \InvalidArgumentException('The operator of a property query must be a string.');
        }

        $this->propertyQuery($query)->where($key, $operator, $value, $boolean);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrWhereProperty(Builder $query, string $key, mixed $operator = null, mixed $value = null): Builder
    {
        return 3 === func_num_args()
            ? $this->scopeWhereProperty($query, $key, '=', $operator, 'or')
            : $this->scopeWhereProperty($query, $key, $operator, $value, 'or');
    }

    /**
     * All conditions must match: `whereProperties(['theme' => 'dark', 'status' => Status::Active])`.
     *
     * @param  Builder<static>  $query
     * @param  array<array-key, mixed>  $conditions
     * @return Builder<static>
     */
    public function scopeWhereProperties(Builder $query, array $conditions): Builder
    {
        $this->propertyQuery($query)->whereAll($conditions);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>  $values
     * @return Builder<static>
     */
    public function scopeWherePropertyIn(Builder $query, string $key, array|Arrayable $values): Builder
    {
        $this->propertyQuery($query)->whereIn($key, $values instanceof Arrayable ? $values->toArray() : $values);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>  $values
     * @return Builder<static>
     */
    public function scopeWherePropertyNotIn(Builder $query, string $key, array|Arrayable $values): Builder
    {
        $this->propertyQuery($query)->whereIn($key, $values instanceof Arrayable ? $values->toArray() : $values, not: true);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWherePropertyNull(Builder $query, string $key): Builder
    {
        $this->propertyQuery($query)->whereNull($key);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWherePropertyNotNull(Builder $query, string $key): Builder
    {
        $this->propertyQuery($query)->whereNull($key, not: true);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<array-key, mixed>  $range
     * @return Builder<static>
     */
    public function scopeWherePropertyBetween(Builder $query, string $key, array $range): Builder
    {
        $this->propertyQuery($query)->whereBetween($key, $range);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<array-key, mixed>  $range
     * @return Builder<static>
     */
    public function scopeWherePropertyNotBetween(Builder $query, string $key, array $range): Builder
    {
        $this->propertyQuery($query)->whereBetween($key, $range, not: true);

        return $query;
    }

    /**
     * The property is stored or has a default (like `hasProperty()`). Works for encrypted properties.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasProperty(Builder $query, string $key): Builder
    {
        $this->propertyQuery($query)->whereHas($key);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereDoesntHaveProperty(Builder $query, string $key): Builder
    {
        $this->propertyQuery($query)->whereHas($key, not: true);

        return $query;
    }

    /**
     * A list property contains the value (or all values of an array).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWherePropertyContains(Builder $query, string $key, mixed $value): Builder
    {
        $this->propertyQuery($query)->whereContains($key, $value);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWherePropertyDoesntContain(Builder $query, string $key, mixed $value): Builder
    {
        $this->propertyQuery($query)->whereContains($key, $value, not: true);

        return $query;
    }

    /**
     * `wherePropertyLength('tags', 3)` or `wherePropertyLength('tags', '>', 1)`.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWherePropertyLength(Builder $query, string $key, int|string $operator, ?int $value = null): Builder
    {
        if (null === $value) {
            [$operator, $value] = ['=', (int) $operator];
        }

        $this->propertyQuery($query)->whereLength($key, (string) $operator, $value);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrderByProperty(Builder $query, string $key, string $direction = 'asc'): Builder
    {
        $this->propertyQuery($query)->orderBy($key, $direction);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrderByPropertyDesc(Builder $query, string $key): Builder
    {
        return $this->scopeOrderByProperty($query, $key, 'desc');
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function propertyQuery(Builder $query): PropertyQuery
    {
        /** @var Builder<Model> $query */
        return new PropertyQuery($query, $this->props());
    }
}
