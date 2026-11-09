<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

class ItemWithDefaults extends Item
{
    /** @var array<string, mixed> */
    protected array $propertyDefaults = [
        'theme'      => 'light',
        'newsletter' => false,
        'ui'         => ['size' => 'md', 'density' => 'normal'],
        'tags'       => ['a', 'b'],
    ];
}
