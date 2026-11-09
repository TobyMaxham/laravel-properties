<?php

declare(strict_types=1);

use TobyMaxham\LaravelProperties\Tests\Fixtures\ItemWithDefaults;

it('falls back to the defaults', function () {
    $item = new ItemWithDefaults;

    expect($item->getProperty('theme'))->toBe('light')
        ->and($item->getProperty('ui.size'))->toBe('md')
        ->and($item->hasProperty('newsletter'))->toBeTrue()
        ->and($item->getProperty('missing', 'fallback'))->toBe('fallback');
});

it('merges stored values recursively into the defaults', function () {
    $item = (new ItemWithDefaults)->setProperties(['theme' => 'dark', 'ui.size' => 'lg', 'tags' => ['c']]);

    expect($item->getProperties())->toBe([
        'theme'      => 'dark',
        'newsletter' => false,
        'ui'         => ['size' => 'lg', 'density' => 'normal'],
        'tags'       => ['c'], // lists replace the default
    ]);
});

it('never stores the defaults', function () {
    $item = ItemWithDefaults::create();

    expect($item->getProperty('theme'))->toBe('light')
        ->and($item->getRawOriginal('properties'))->toBeNull()
        ->and($item->getStoredProperties())->toBe([])
        ->and($item->isDirty())->toBeFalse();

    $item->setProperty('theme', 'dark')->save();

    expect($item->getRawOriginal('properties'))->toBe('{"theme":"dark"}');
});

it('uses the default again after forgetting a property', function () {
    $item = (new ItemWithDefaults)->setProperty('theme', 'dark')->forgetProperty('theme');

    expect($item->getProperty('theme'))->toBe('light');
});

it('works with list defaults', function () {
    $item = (new ItemWithDefaults)->pushProperty('tags', 'c');

    expect($item->getProperty('tags'))->toBe(['a', 'b', 'c']);
});
