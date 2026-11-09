<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

it('adds a nullable json column', function () {
    $table = new Blueprint(Schema::getConnection(), 'test');

    $columns = [
        $table->properties(),
        $table->properties('settings', jsonb: true),
        $table->properties('secret', encrypted: true),
    ];

    expect(array_map(fn ($column) => [$column->get('name'), $column->get('type'), $column->get('nullable')], $columns))->toBe([
        ['properties', 'json', true],
        ['settings', 'jsonb', true],
        ['secret', 'text', true],
    ]);
});

it('does not allow encrypted jsonb columns', function () {
    (new Blueprint(Schema::getConnection(), 'test'))->properties(encrypted: true, jsonb: true);
})->throws(InvalidArgumentException::class, 'can not use jsonb');

it('drops the column', function () {
    Schema::table('items', fn (Blueprint $table) => $table->dropProperties('meta'));

    expect(Schema::hasColumn('items', 'meta'))->toBeFalse()
        ->and(Schema::hasColumn('items', 'properties'))->toBeTrue();
});

it('created nullable columns in the test table', function () {
    $columns = collect(Schema::getColumns('items'))->keyBy('name');

    expect($columns['properties']['nullable'])->toBeTrue()
        ->and($columns['secret']['type_name'])->toBe('text');
});
