<?php

declare(strict_types=1);

use TobyMaxham\LaravelProperties\Tests\Fixtures\QueryItem;

/*
 * The generated SQL per database. The connections never connect, `toSql()` does not need a database.
 * The real queries are tested in QueryScopesTest on SQLite, MySQL, MariaDB and PostgreSQL (see the workflow).
 */
beforeEach(function () {
    foreach (['mysql', 'mariadb', 'pgsql', 'sqlsrv'] as $driver) {
        config(["database.connections.fake_{$driver}" => ['driver' => $driver, 'host' => 'invalid', 'database' => 'invalid', 'prefix' => '']]);
    }
});

it('compares numbers as numbers', function (string $driver, string $sql) {
    $query = QueryItem::on("fake_{$driver}")->whereProperty('score', '>', 9.5);

    expect($query->toSql())->toBe($sql)
        ->and($query->getBindings())->toBe([9.5]);
})->with([
    'mysql'   => ['mysql', 'select * from `items` where (cast(json_unquote(json_extract(`items`.`properties`, \'$."score"\')) as double) > ? and (json_extract(`items`.`properties`, \'$."score"\') is not null AND json_type(json_extract(`items`.`properties`, \'$."score"\')) != \'NULL\'))'],
    'mariadb' => ['mariadb', 'select * from `items` where (cast(json_value(`items`.`properties`, \'$."score"\') as double) > ? and (json_extract(`items`.`properties`, \'$."score"\') is not null AND json_type(json_extract(`items`.`properties`, \'$."score"\')) != \'NULL\'))'],
    'pgsql'   => ['pgsql', 'select * from "items" where (case when ("items"."properties"->>\'score\') ~ \'^-?[0-9]+(\\.[0-9]+)?([eE][-+]?[0-9]+)?$\' then ("items"."properties"->>\'score\')::numeric end) > ?'],
    'sqlsrv'  => ['sqlsrv', 'select * from [items] where try_cast(json_value([items].[properties], \'$."score"\') as float) > ?'],
]);

it('orders numbers as numbers with the default', function () {
    $query = QueryItem::on('fake_pgsql')->orderByProperty('logins', 'desc');

    expect($query->toSql())->toContain('order by coalesce((case when')
        ->and($query->toSql())->toEndWith('::numeric end), ?) desc')
        ->and($query->getBindings())->toBe([0]);
});
