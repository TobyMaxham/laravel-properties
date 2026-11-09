<?php

declare(strict_types=1);

use TobyMaxham\LaravelProperties\Tests\Fixtures\ObjectItem;
use TobyMaxham\LaravelProperties\Tests\Fixtures\ItemWithDefaults;
use TobyMaxham\LaravelProperties\Tests\Fixtures\ItemSerializingDefaults;

it('only serializes the stored properties by default', function () {
    $item = (new ItemWithDefaults)->setProperty('theme', 'dark');

    expect($item->toArray()['properties'])->toBe(['theme' => 'dark'])
        ->and($item->toJson())->toContain('"properties":{"theme":"dark"}');
});

it('serializes the defaults when the model wants it', function () {
    $item = (new ItemSerializingDefaults)->setProperty('size', 'lg');

    expect($item->toArray()['properties'])->toBe(['theme' => 'light', 'size' => 'lg']);
});

it('switches the defaults per instance', function () {
    $item = (new ItemWithDefaults)->setProperty('theme', 'dark');

    expect($item->withPropertyDefaults()->toArray()['properties'])->toMatchArray(['theme' => 'dark', 'newsletter' => false])
        ->and($item->withoutPropertyDefaults()->toArray()['properties'])->toBe(['theme' => 'dark'])
        ->and((new ItemSerializingDefaults)->forceFill(['properties' => null])->withoutPropertyDefaults()->toArray()['properties'])->toBe([]);
});

it('serializes the defaults of a model without stored properties', function () {
    expect((new ItemSerializingDefaults)->forceFill(['properties' => null])->toArray()['properties'])->toBe(['theme' => 'light']);
});

it('serializes objects as arrays', function () {
    $item = (new ObjectItem)->setProperty('a', 1);

    expect($item->toArray()['properties'])->toBe(['a' => 1]);
});
