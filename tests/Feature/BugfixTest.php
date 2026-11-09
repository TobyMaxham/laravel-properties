<?php

declare(strict_types=1);

use TobyMaxham\LaravelProperties\Tests\Fixtures\Item;
use TobyMaxham\LaravelProperties\Tests\Fixtures\LegacyItem;
use TobyMaxham\LaravelProperties\Tests\Fixtures\ItemWithColumnMethod;
use TobyMaxham\LaravelProperties\Tests\Fixtures\ItemWithColumnConstant;
use TobyMaxham\LaravelProperties\Tests\Fixtures\ItemWithColumnProperty;

// 1.x ignored the default of getProperty().
it('returns the default for missing properties', function () {
    $item = new Item;
    $item->setProperty('foo.bar', 'value');

    expect($item->getProperty('foo.baz', 'another value'))->toBe('another value')
        ->and($item->getProperty('missing', fn () => 'lazy'))->toBe('lazy')
        ->and($item->getProperty('foo.bar', 'default'))->toBe('value');
});

it('keeps a stored null instead of the default', function () {
    $item = (new Item)->setProperty('foo', null);

    expect($item->getProperty('foo', 'default'))->toBeNull();
});

// 1.x needed a manual array cast, otherwise it failed silently.
it('works without a cast in the model', function () {
    $item = Item::create(['name' => 'test']);
    $item->setProperty('foo', 'bar')->save();

    expect(Item::find($item->id)->getProperty('foo'))->toBe('bar');
});

// 1.x: redeclaring the column property was a fatal error.
it('uses a custom column', function (string $class, string $column) {
    $item = new $class;
    $item->setProperty('foo', 'bar')->save();

    expect($item->propertiesColumn())->toBe($column)
        ->and($item->getRawOriginal($column))->toBe('{"foo":"bar"}')
        ->and($item->getRawOriginal('properties'))->toBeNull()
        ->and($class::find($item->id)->getProperty('foo'))->toBe('bar');
})->with([
    'constant'     => [ItemWithColumnConstant::class, 'settings'],
    'property'     => [ItemWithColumnProperty::class, 'settings'],
    'method'       => [ItemWithColumnMethod::class, 'meta'],
    '1.x property' => [LegacyItem::class, 'meta'],
]);

// The column of the 1.x readme was not nullable.
it('creates models without properties', function () {
    $item = Item::create(['name' => 'test']);

    expect($item->getRawOriginal('properties'))->toBeNull()
        ->and($item->getProperties())->toBe([]);
});
