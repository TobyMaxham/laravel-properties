<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(fn () => File::deleteDirectory(database_path('migrations/properties-test')));
afterEach(fn () => File::deleteDirectory(database_path('migrations/properties-test')));

function createdMigration(): string
{
    $files = File::files(database_path('migrations/properties-test'));

    expect($files)->toHaveCount(1);

    return $files[0]->getPathname();
}

it('creates a migration for the properties column', function () {
    $this->artisan('make:properties-migration', ['table' => 'items', '--column' => 'extra', '--index' => ['ui.theme'], '--path' => 'database/migrations/properties-test'])
        ->assertSuccessful();

    $file = createdMigration();

    expect(basename($file))->toEndWith('_add_extra_to_items_table.php')
        ->and(file_get_contents($file))
        ->toContain("\$table->properties('extra');")
        ->toContain("\$table->propertyIndex('ui.theme', 'extra');")
        ->toContain("\$table->dropPropertyIndex('ui.theme', 'extra');")
        ->toContain("\$table->dropProperties('extra');");

    $migration = require $file;
    $migration->up();

    expect(Schema::hasColumn('items', 'extra'))->toBeTrue();

    $migration->down();

    expect(Schema::hasColumn('items', 'extra'))->toBeFalse();
});

it('creates migrations for encrypted and jsonb columns', function (array $options, string $expected) {
    $this->artisan('make:properties-migration', ['table' => 'users', '--path' => 'database/migrations/properties-test', ...$options])
        ->assertSuccessful();

    expect(file_get_contents(createdMigration()))
        ->toContain($expected)
        ->toContain('$table->dropProperties();');
})->with([
    'default'   => [[], '$table->properties();'],
    'encrypted' => [['--encrypted' => true], '$table->properties(encrypted: true);'],
    'jsonb'     => [['--jsonb' => true], '$table->properties(jsonb: true);'],
]);

it('uses the migrations folder by default', function () {
    $this->artisan('make:properties-migration', ['table' => 'users'])->assertSuccessful();

    $files = File::glob(database_path('migrations/*_add_properties_to_users_table.php'));

    expect($files)->toHaveCount(1);

    File::delete($files);
});

it('does not allow jsonb or indexes for encrypted columns', function () {
    $this->artisan('make:properties-migration', ['table' => 'users', '--encrypted' => true, '--jsonb' => true])
        ->expectsOutputToContain('Encrypted properties are stored as text')
        ->assertFailed();
});
