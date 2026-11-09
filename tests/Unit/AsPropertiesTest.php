<?php

declare(strict_types=1);

use TobyMaxham\LaravelProperties\Casts\AsProperties;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Item;

it('compares null values', function () {
    $cast = new AsProperties;

    expect($cast->compare(new Item, 'properties', null, null))->toBeTrue()
        ->and($cast->compare(new Item, 'properties', null, '{}'))->toBeFalse()
        ->and($cast->compare(new Item, 'properties', '{}', null))->toBeFalse();
});

it('decodes values that are already arrays', function () {
    expect((new AsProperties)->decode(new Item, 'properties', ['a' => 1]))->toBe(['a' => 1]);
});

it('marks a property change from null as dirty', function () {
    $item = Item::create(['properties' => null]);

    expect($item->setProperty('a', 1)->isDirty('properties'))->toBeTrue();
});
