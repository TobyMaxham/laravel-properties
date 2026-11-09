<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

class ItemWithColumnConstant extends Item
{
    public const string PROPERTIES_COLUMN = 'settings';
}
