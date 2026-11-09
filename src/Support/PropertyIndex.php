<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Support;

use InvalidArgumentException;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Index for a single property, used by `$table->propertyIndex()`.
 *
 * The index uses exactly the expression of the query scopes (`whereProperty()` etc.),
 * otherwise the database would not use it:
 *
 * - MySQL / MariaDB: a virtual generated column with an index (the optimizer uses it for the JSON expression)
 * - PostgreSQL / SQLite: an expression index
 *
 * @internal
 */
final class PropertyIndex
{
    public static function add(Blueprint $table, Connection $connection, string $key, string $column, ?string $name, int $length): void
    {
        $name ??= self::name($table, $connection, $column, $key);
        $expression = self::expression($connection, $column, $key);

        match ($connection->getDriverName()) {
            'mysql', 'mariadb' => self::addGeneratedColumn($table, $expression, self::generatedColumn($column, $key), $name, $length),
            'pgsql'            => $table->rawIndex('('.$expression.')', $name),
            'sqlite'           => $table->rawIndex($expression, $name),
            default            => throw self::unsupported($connection),
        };
    }

    public static function drop(Blueprint $table, Connection $connection, string $key, string $column, ?string $name): void
    {
        $name ??= self::name($table, $connection, $column, $key);

        match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $table->dropColumn(self::generatedColumn($column, $key)), // drops the index, too
            'pgsql', 'sqlite'  => $table->dropIndex($name),
            default            => throw self::unsupported($connection),
        };
    }

    /**
     * `ui.size` on `properties` → `properties_ui_size`.
     */
    public static function generatedColumn(string $column, string $key): string
    {
        return $column.'_'.self::slug($key);
    }

    /**
     * Like Laravel's index names: `items_properties_ui_size_index`.
     */
    public static function name(Blueprint $table, Connection $connection, string $column, string $key): string
    {
        $prefix = $connection->getConfig('prefix_indexes') ? $connection->getTablePrefix() : '';

        return str_replace(['-', '.'], '_', strtolower($prefix.$table->getTable().'_'.$column.'_'.self::slug($key).'_index'));
    }

    /**
     * The JSON expression of the query scopes, e.g. `json_unquote(json_extract(`properties`, '$."ui"."size"'))`.
     */
    public static function expression(Connection $connection, string $column, string $key): string
    {
        $path = $column;

        foreach (explode('.', $key) as $index => $segment) {
            $path .= $index > 0 && ctype_digit($segment) ? '['.$segment.']' : '->'.$segment;
        }

        return (string) $connection->getQueryGrammar()->wrap($path);
    }

    private static function addGeneratedColumn(Blueprint $table, string $expression, string $generated, string $name, int $length): void
    {
        $table->string($generated, $length)->virtualAs($expression)->nullable();
        $table->index($generated, $name);
    }

    private static function slug(string $key): string
    {
        return (string) preg_replace('/[^a-z0-9]+/i', '_', $key);
    }

    private static function unsupported(Connection $connection): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'Property indexes are not supported for [%s]. Supported: MySQL, MariaDB, PostgreSQL and SQLite.',
            $connection->getDriverName(),
        ));
    }
}
