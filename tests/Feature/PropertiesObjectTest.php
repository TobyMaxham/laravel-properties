<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use TobyMaxham\LaravelProperties\Properties;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Item;
use TobyMaxham\LaravelProperties\Tests\Fixtures\ItemWithDefaults;

it('returns a properties object bound to the model', function () {
    $item = new Item;
    $props = $item->props();

    expect($props)->toBeInstanceOf(Properties::class)
        ->and($props->model())->toBe($item);
});

it('reads and writes with methods', function () {
    $item = new ItemWithDefaults;

    $item->props()
        ->set('theme', 'dark')
        ->set(['ui.size' => 'lg', 'count' => 1])
        ->merge(['other' => true])
        ->increment('count', 2)
        ->decrement('count')
        ->toggle('newsletter')
        ->push('tags', 'c')
        ->forget('other');

    expect($item->props()->get('theme'))->toBe('dark')
        ->and($item->props()->get('missing', 'x'))->toBe('x')
        ->and($item->props()->has('ui.size'))->toBeTrue()
        ->and($item->props()->stored())->toBe(['theme' => 'dark', 'ui' => ['size' => 'lg'], 'count' => 2, 'newsletter' => true, 'tags' => ['a', 'b', 'c']])
        ->and($item->props()->defaults())->toBe($item->propertyDefaults())
        ->and($item->props()->only('theme'))->toBe(['theme' => 'dark'])
        ->and($item->props()->except(['ui', 'tags', 'count', 'newsletter']))->toBe(['theme' => 'dark'])
        ->and($item->props()->pull('count'))->toBe(2)
        ->and($item->props()->replace(['a' => 1])->stored())->toBe(['a' => 1])
        ->and($item->props()->clear()->stored())->toBe([]);
});

it('supports array access', function () {
    $props = (new Item)->props();

    $props['ui.theme'] = 'dark';

    expect($props['ui.theme'])->toBe('dark')
        ->and(isset($props['ui.theme']))->toBeTrue()
        ->and(isset($props['missing']))->toBeFalse();

    unset($props['ui.theme']);

    expect($props->all())->toBe(['ui' => []]);
});

it('needs a key for array access', function () {
    $props = (new Item)->props();
    $props[] = 'value';
})->throws(InvalidArgumentException::class, 'Properties need a key');

it('supports magic properties', function () {
    $props = (new Item)->props();

    $props->theme = 'dark';
    $props->empty = null;

    expect($props->theme)->toBe('dark')
        ->and(isset($props->theme))->toBeTrue()
        ->and(isset($props->empty))->toBeFalse();

    unset($props->theme);

    expect($props->theme)->toBeNull();
});

it('is countable, iterable, arrayable and json serializable', function () {
    $props = (new Item)->setProperties(['a' => 1, 'b' => 2])->props();

    expect($props)->toHaveCount(2)
        ->and(iterator_to_array($props))->toBe(['a' => 1, 'b' => 2])
        ->and($props->toArray())->toBe(['a' => 1, 'b' => 2])
        ->and($props->collect())->toBeInstanceOf(Collection::class)
        ->and(json_encode($props))->toBe('{"a":1,"b":2}')
        ->and($props->toJson())->toBe('{"a":1,"b":2}');
});

it('knows its column', function () {
    expect((new Item)->props()->column())->toBe('properties');
});
