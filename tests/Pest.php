<?php

declare(strict_types=1);

use TobyMaxham\LaravelProperties\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Like `toBe()`, but ignores the order of keys. MySQL reorders the keys of JSON objects.
 */
expect()->extend('toBeProperties', function (array $expected) {
    $sort = function (array $value) use (&$sort): array {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item) => is_array($item) ? $sort($item) : $item, $value);
    };

    return expect($sort($this->value))->toBe($sort($expected));
});
