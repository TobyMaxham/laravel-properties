<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

class ItemWithColumnProperty extends Item
{
    protected string $propertiesColumn = 'settings';
}
