<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use TobyMaxham\LaravelProperties\Support\PropertyIndex;
use TobyMaxham\LaravelProperties\Tests\Fixtures\QueryItem;

it('uses the index for property queries', function () {
    if ('mariadb' === DB::getDriverName()) {
        $this->markTestSkipped('MariaDB does not use generated columns for JSON expressions, see the next test.');
    }

    Schema::table('items', fn (Blueprint $table) => $table->propertyIndex('ui.size'));

    foreach (range(1, 50) as $i) {
        QueryItem::create(['name' => "item {$i}"])->setProperty('ui.size', "size {$i}")->save();
    }

    $query = QueryItem::whereProperty('ui.size', 'size 7');
    $plan = explainQuery($query->toSql(), $query->getBindings());

    expect($query->pluck('name')->all())->toBe(['item 7'])
        ->and($plan)->toContain('items_properties_ui_size_index');
});

function explainQuery(string $sql, array $bindings): string
{
    return match (DB::getDriverName()) {
        'sqlite' => json_encode(DB::select('explain query plan '.$sql, $bindings)),
        'pgsql'  => (function () use ($sql, $bindings) {
            DB::statement('set enable_seqscan = off');

            return json_encode(DB::select('explain '.$sql, $bindings));
        })(),
        default => json_encode(DB::select('explain '.$sql, $bindings)),
    };
}

it('uses the generated column on MariaDB', function () {
    Schema::table('items', fn (Blueprint $table) => $table->propertyIndex('ui.size'));
    QueryItem::create()->setProperty('ui.size', 'lg')->save();

    expect(explainQuery('select * from items where properties_ui_size = ?', ['lg']))->toContain('items_properties_ui_size_index')
        ->and(DB::table('items')->where('properties_ui_size', 'lg')->count())->toBe(1);
})->skip(fn () => ! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true), 'Only MySQL and MariaDB use a generated column.');

it('drops the index', function () {
    Schema::table('items', fn (Blueprint $table) => $table->propertyIndex('status', 'settings', name: 'status_index'));
    Schema::table('items', fn (Blueprint $table) => $table->dropPropertyIndex('status', 'settings', name: 'status_index'));
    Schema::table('items', fn (Blueprint $table) => $table->propertyIndex('ui.size'));
    Schema::table('items', fn (Blueprint $table) => $table->dropPropertyIndex('ui.size'));

    expect(collect(Schema::getIndexes('items'))->pluck('name')->filter(fn (string $name) => str_contains($name, 'status') || str_contains($name, 'ui_size'))->all())->toBe([])
        ->and(Schema::hasColumn('items', 'properties_ui_size'))->toBeFalse();
});

it('names the index like Laravel', function () {
    $connection = DB::connection();
    $table = new Blueprint($connection, 'items');

    expect(PropertyIndex::name($table, $connection, 'properties', 'ui.size-x'))->toBe('items_properties_ui_size_x_index')
        ->and(PropertyIndex::generatedColumn('settings', 'ui.size'))->toBe('settings_ui_size')
        ->and(PropertyIndex::expression($connection, 'properties', 'tags.0'))->toContain('tags');
});

it('does not support other databases', function () {
    config(['database.connections.fake_sqlsrv' => ['driver' => 'sqlsrv', 'host' => 'invalid', 'database' => 'invalid', 'prefix' => '']]);
    $connection = DB::connection('fake_sqlsrv');
    $connection->useDefaultSchemaGrammar();

    expect(fn () => PropertyIndex::add(new Blueprint($connection, 'items'), $connection, 'a', 'properties', null, 191))
        ->toThrow(InvalidArgumentException::class, 'Property indexes are not supported for [sqlsrv].')
        ->and(fn () => PropertyIndex::drop(new Blueprint($connection, 'items'), $connection, 'a', 'properties', null))
        ->toThrow(InvalidArgumentException::class, 'not supported');
});

it('creates the schema per database', function (string $driver, array $add, array $drop) {
    config(["database.connections.fake_{$driver}" => ['driver' => $driver, 'host' => 'invalid', 'database' => 'invalid', 'prefix' => '']]);
    $connection = DB::connection("fake_{$driver}");
    $connection->useDefaultSchemaGrammar();

    $blueprint = new Blueprint($connection, 'items');
    $blueprint->propertyIndex('ui.size');

    $dropBlueprint = new Blueprint($connection, 'items');
    $dropBlueprint->dropPropertyIndex('ui.size');

    expect($blueprint->toSql())->toBe($add)
        ->and($dropBlueprint->toSql())->toBe($drop);
})->with([
    'mysql' => ['mysql', [
        'alter table `items` add `properties_ui_size` varchar(191) as (json_unquote(json_extract(`properties`, \'$."ui"."size"\')))',
        'alter table `items` add index `items_properties_ui_size_index`(`properties_ui_size`)',
    ], ['alter table `items` drop `properties_ui_size`']],
    'pgsql' => ['pgsql', [
        'create index "items_properties_ui_size_index" on "items" (("properties"->\'ui\'->>\'size\'))',
    ], ['drop index "items_properties_ui_size_index"']],
]);
