<?php

declare(strict_types=1);

use TobyMaxham\LaravelProperties\Tests\Fixtures\Item;
use TobyMaxham\LaravelProperties\Exceptions\PropertiesException;
use TobyMaxham\LaravelProperties\Exceptions\InvalidPropertyValueException;

it('sets and gets properties with dot notation', function () {
    $item = (new Item)->setProperty('ui.theme', 'dark')->setProperty('count', 3);

    expect($item->getProperty('ui.theme'))->toBe('dark')
        ->and($item->getProperty('ui'))->toBe(['theme' => 'dark'])
        ->and($item->getProperties())->toBe(['ui' => ['theme' => 'dark'], 'count' => 3])
        ->and($item->properties)->toBe(['ui' => ['theme' => 'dark'], 'count' => 3]);
});

it('checks whether a property exists', function () {
    $item = (new Item)->setProperty('a.b', null);

    expect($item->hasProperty('a.b'))->toBeTrue()
        ->and($item->hasProperty('a'))->toBeTrue()
        ->and($item->hasProperty('a.c'))->toBeFalse();
});

it('sets several properties and keeps the others', function () {
    $item = (new Item)->setProperty('keep', 1)->setProperties(['a.b' => 2, 'c' => 3]);

    expect($item->getProperties())->toBe(['keep' => 1, 'a' => ['b' => 2], 'c' => 3]);
});

it('replaces all properties', function () {
    $item = (new Item)->setProperty('old', 1)->replaceProperties(['new' => 2]);

    expect($item->getProperties())->toBe(['new' => 2]);
});

it('forgets properties', function () {
    $item = (new Item)->setProperties(['a' => 1, 'b' => ['c' => 2, 'd' => 3], 'e' => 4]);

    $item->forgetProperty('a')->forgetProperty(['b.c', 'e']);

    expect($item->getProperties())->toBe(['b' => ['d' => 3]]);
});

it('clears properties and stores an empty object', function () {
    $item = Item::create();
    $item->setProperty('a', 1)->clearProperties()->save();

    expect($item->getProperties())->toBe([])
        ->and($item->getRawOriginal('properties'))->toBe('{}');
});

it('keeps emptyProperties() of 1.x', function () {
    $item = (new Item)->setProperty('a', 1);

    expect($item->emptyProperties())->toBe(['a' => 1])
        ->and($item->getProperties())->toBe([]);
});

it('pushes values to a list', function () {
    $item = (new Item)->pushProperty('tags', 'a')->pushProperty('tags', 'b', 'c');

    expect($item->getProperty('tags'))->toBe(['a', 'b', 'c']);
});

it('only pushes to lists', function () {
    (new Item)->setProperty('tags', ['a' => 1])->pushProperty('tags', 'b');
})->throws(InvalidPropertyValueException::class, 'The property [tags] of ['.Item::class.'] must be a list, [array] given.');

it('pulls a property', function () {
    $item = (new Item)->setProperties(['a' => 1, 'b' => 2]);

    expect($item->pullProperty('a'))->toBe(1)
        ->and($item->pullProperty('missing', 'default'))->toBe('default')
        ->and($item->getProperties())->toBe(['b' => 2]);
});

it('increments and decrements numbers', function () {
    $item = (new Item)->incrementProperty('count')->incrementProperty('count', 5)->decrementProperty('count', 2);
    $item->incrementProperty('price', 1.5);

    expect($item->getProperty('count'))->toBe(4)
        ->and($item->getProperty('price'))->toBe(1.5);
});

it('only increments numbers', function () {
    (new Item)->setProperty('count', '5')->incrementProperty('count');
})->throws(InvalidPropertyValueException::class, 'must be a number, [string] given');

it('toggles booleans', function () {
    $item = (new Item)->toggleProperty('active');

    expect($item->getProperty('active'))->toBeTrue()
        ->and($item->toggleProperty('active')->getProperty('active'))->toBeFalse();
});

it('only toggles booleans', function () {
    (new Item)->setProperty('active', 1)->toggleProperty('active');
})->throws(InvalidPropertyValueException::class, 'must be a boolean, [int] given');

it('returns only or except some properties', function () {
    $item = (new Item)->setProperties(['a' => 1, 'b' => ['c' => 2, 'd' => 3], 'e' => 4]);

    expect($item->onlyProperties(['a', 'b.c', 'missing']))->toBe(['a' => 1, 'b' => ['c' => 2]])
        ->and($item->onlyProperties('e'))->toBe(['e' => 4])
        ->and($item->exceptProperties(['a', 'b.c']))->toBe(['b' => ['d' => 3], 'e' => 4]);
});

it('throws package exceptions', function () {
    expect(new InvalidPropertyValueException)->toBeInstanceOf(PropertiesException::class);
});
