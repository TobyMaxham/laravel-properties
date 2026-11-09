<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Migrations;

use Closure;
use stdClass;
use JsonException;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use TobyMaxham\LaravelProperties\Support\PropertiesArray;
use TobyMaxham\LaravelProperties\Exceptions\PropertiesException;

/**
 * Change the properties of all rows, e.g. in a migration. Runs in chunks, without model events and timestamps.
 *
 * ```php
 * Properties::table('users')                  // plain JSON, without casts
 *     ->rename('theme', 'ui.theme')
 *     ->forget('legacy_flag')
 *     ->set('newsletter', false, onlyIfMissing: true)
 *     ->moveIn(['locale' => 'ui.locale'])     // column → property (drop the column afterwards)
 *     ->run();
 *
 * Properties::model(User::class)              // with the casts and encryption of the model
 *     ->set('status', Status::Active, onlyIfMissing: true)
 *     ->run();
 * ```
 *
 * The operations work on the stored values: `rename()` and `moveOut()` move them as they are,
 * `set()`, `transform()` and `moveIn()` convert new values with the property casts (only for `model()`).
 */
final class PropertiesMigrator
{
    /** @var list<Closure(array<array-key, mixed>, object): array<array-key, mixed>> */
    private array $operations = [];

    /** @var array<string, string> column → property */
    private array $moveIn = [];

    /** @var array<string, string> property → column */
    private array $moveOut = [];

    private ?Closure $constraint = null;

    /** @var array<string, mixed> columns to update for the current row (table mode: value, model mode: true) */
    private array $columns = [];

    /**
     * @param  class-string<Model>|null  $model
     */
    private function __construct(
        private readonly ?string $model,
        private readonly string $table,
        private readonly string $column,
        private readonly ?string $connection,
        private readonly string $primaryKey,
    ) {}

    /**
     * Plain JSON of a table, without model, casts or encryption.
     */
    public static function forTable(string $table, string $column = 'properties', ?string $connection = null, string $primaryKey = 'id'): self
    {
        return new self(null, $table, $column, $connection, $primaryKey);
    }

    /**
     * Uses the column, casts and encryption of the model.
     *
     * @param  class-string  $model
     */
    public static function forModel(string $model): self
    {
        $instance = is_subclass_of($model, Model::class) ? new $model : null;

        if (null === $instance || ! method_exists($instance, 'propertiesColumn') || ! is_string($column = $instance->propertiesColumn())) {
            throw new InvalidArgumentException(sprintf('The model [%s] must use the HasProperties trait.', $model));
        }

        return new self($model, $instance->getTable(), $column, $instance->getConnectionName(), $instance->getKeyName());
    }

    /**
     * Only change some rows: `->where(fn ($query) => $query->where('active', true))`.
     *
     * @param  Closure(QueryBuilder|Builder<covariant Model>): mixed  $constraint
     */
    public function where(Closure $constraint): self
    {
        $this->constraint = $constraint;

        return $this;
    }

    /**
     * Move a property (keys may use "dot" notation). Existing values at `$to` are overwritten.
     */
    public function rename(string $from, string $to): self
    {
        return $this->operation(function (array $properties) use ($from, $to) {
            if (Arr::has($properties, $from)) {
                $value = Arr::get($properties, $from);
                Arr::forget($properties, $from);
                Arr::set($properties, $to, $value);
            }

            return $properties;
        });
    }

    /**
     * @param  string|array<array-key, string>  $keys
     */
    public function forget(string|array $keys): self
    {
        return $this->operation(function (array $properties) use ($keys) {
            Arr::forget($properties, $keys);

            return $properties;
        });
    }

    /**
     * Set a property. `$value` can be a closure: `fn (array $properties, object $row) => ...`.
     */
    public function set(string $key, mixed $value, bool $onlyIfMissing = false): self
    {
        return $this->operation(function (array $properties, object $row) use ($key, $value, $onlyIfMissing) {
            if ($onlyIfMissing && Arr::has($properties, $key)) {
                return $properties;
            }

            $value = $value instanceof Closure ? $value($properties, $row) : $value;
            Arr::set($properties, $key, $this->toStorage($row, $key, $value));

            return $properties;
        });
    }

    /**
     * Change an existing property: `->transform('price', fn ($cents) => $cents / 100)`.
     * The callback gets the stored value and the row.
     */
    public function transform(string $key, Closure $callback): self
    {
        return $this->operation(function (array $properties, object $row) use ($key, $callback) {
            if (Arr::has($properties, $key)) {
                Arr::set($properties, $key, $this->toStorage($row, $key, $callback(Arr::get($properties, $key), $row)));
            }

            return $properties;
        });
    }

    /**
     * Copy columns into properties: `['newsletter', 'locale' => 'ui.locale']`. Drop the columns afterwards.
     * `null` values are not copied.
     *
     * @param  string|array<array-key, string>  $columns
     */
    public function moveIn(string|array $columns): self
    {
        foreach ((array) $columns as $column => $key) {
            $this->moveIn[is_int($column) ? $key : $column] = $key;
        }

        return $this->operation(function (array $properties, object $row) {
            foreach ($this->moveIn as $column => $key) {
                $value = $this->columnValue($row, $column);

                if (null !== $value) {
                    Arr::set($properties, $key, $this->toStorage($row, $key, $value));
                }
            }

            return $properties;
        });
    }

    /**
     * Move properties into existing columns: `['newsletter', 'ui.locale' => 'locale']`. The properties are removed.
     * Rows without the property get `null` in the column.
     *
     * @param  string|array<array-key, string>  $keys
     */
    public function moveOut(string|array $keys): self
    {
        foreach ((array) $keys as $key => $column) {
            $this->moveOut[is_int($key) ? $column : $key] = $column;
        }

        return $this->operation(function (array $properties, object $row) {
            foreach ($this->moveOut as $key => $column) {
                $this->setColumnValue($row, $column, Arr::get($properties, $key));
                Arr::forget($properties, $key);
            }

            return $properties;
        });
    }

    /**
     * Run all operations. Returns the number of changed rows.
     */
    public function run(int $chunkSize = 500): int
    {
        if ([] === $this->operations) {
            return 0;
        }

        $changed = 0;

        $this->query()->chunkById($chunkSize, function (iterable $rows) use (&$changed) {
            foreach ($rows as $row) {
                $changed += (int) $this->runRow($row);
            }
        }, $this->primaryKey);

        return $changed;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Closure(array<array-key, mixed>, object): array<array-key, mixed>  $operation
     */
    private function operation(Closure $operation): self
    {
        $this->operations[] = $operation;

        return $this;
    }

    /**
     * @return QueryBuilder|Builder<covariant Model>
     */
    private function query(): QueryBuilder|Builder
    {
        $query = null !== $this->model
            ? $this->model::query()->withoutGlobalScopes() // e.g. also soft deleted rows
            : DB::connection($this->connection)->table($this->table)
                ->select(array_values(array_unique([$this->primaryKey, $this->column, ...array_keys($this->moveIn)])));

        if (null !== $this->constraint) {
            ($this->constraint)($query);
        }

        return $query;
    }

    private function runRow(object $row): bool
    {
        $original = $properties = $this->stored($row);
        $this->columns = [];

        foreach ($this->operations as $operation) {
            $properties = $operation($properties, $row);
        }

        $columns = $this->columns;

        if ($properties === $original && [] === $columns) {
            return false;
        }

        if ($row instanceof Model) {
            $row->setAttribute($this->column, $properties);
            $row->newQueryWithoutScopes()->toBase()->where($row->getKeyName(), $row->getKey())->update($row->getDirty());

            return true;
        }

        if (! $row instanceof stdClass) {
            return false; // @codeCoverageIgnore
        }

        DB::connection($this->connection)->table($this->table)
            ->where($this->primaryKey, $row->{$this->primaryKey})
            ->update([$this->column => PropertiesArray::toJson($properties), ...$columns]);

        return true;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function stored(object $row): array
    {
        if ($row instanceof Model && method_exists($row, 'getStoredProperties')) {
            /** @var array<array-key, mixed> */
            return $row->getStoredProperties();
        }

        $value = $row->{$this->column} ?? null;

        try {
            $decoded = null === $value || '' === $value ? [] : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PropertiesException(sprintf('The column [%s.%s] of the row [%s] does not contain valid JSON: %s', $this->table, $this->column, $row->{$this->primaryKey} ?? '?', $exception->getMessage()));
        }

        if (! is_array($decoded)) {
            throw new PropertiesException(sprintf('The column [%s.%s] of the row [%s] must contain a JSON object or array.', $this->table, $this->column, $row->{$this->primaryKey} ?? '?'));
        }

        return $decoded;
    }

    private function toStorage(object $row, string $key, mixed $value): mixed
    {
        return $row instanceof Model && method_exists($row, 'props') ? $row->props()->toStorage($key, $value) : $value;
    }

    private function columnValue(object $row, string $column): mixed
    {
        return $row instanceof Model ? $row->getAttribute($column) : ($row->{$column} ?? null);
    }

    private function setColumnValue(object $row, string $column, mixed $value): void
    {
        if ($row instanceof Model) {
            $row->setAttribute($column, $value);
            $this->columns[$column] = true;

            return;
        }

        $this->columns[$column] = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
    }
}
