<?php

declare(strict_types=1);

use TobyMaxham\LaravelProperties\Support\PropertiesArray;

it('merges defaults', function (array $defaults, array $stored, array $expected) {
    expect(PropertiesArray::mergeDefaults($defaults, $stored))->toBe($expected);
})->with([
    'nested'          => [['a' => ['b' => 1, 'c' => 2]], ['a' => ['b' => 3]], ['a' => ['b' => 3, 'c' => 2]]],
    'list replaces'   => [['a' => [1, 2]], ['a' => [3]], ['a' => [3]]],
    'scalar replaces' => [['a' => ['b' => 1]], ['a' => 'text'], ['a' => 'text']],
    'empty array'     => [['a' => ['b' => 1]], ['a' => []], ['a' => ['b' => 1]]],
    'null is kept'    => [['a' => 1], ['a' => null], ['a' => null]],
    'new keys'        => [['a' => 1], ['b' => 2], ['a' => 1, 'b' => 2]],
]);

it('compares arrays without key order', function () {
    expect(PropertiesArray::equals(['a' => 1, 'b' => ['d' => 1, 'c' => 2]], ['b' => ['c' => 2, 'd' => 1], 'a' => 1]))->toBeTrue()
        ->and(PropertiesArray::equals([1, 2], [2, 1]))->toBeFalse()
        ->and(PropertiesArray::equals(['a' => 1], ['a' => '1']))->toBeFalse();
});

it('encodes empty properties as an object', function () {
    expect(PropertiesArray::toJson([]))->toBe('{}');
});

it('returns null for values that are no arrays', function () {
    expect(PropertiesArray::normalize('text'))->toBeNull()
        ->and(PropertiesArray::normalize(null))->toBe([]);
});
