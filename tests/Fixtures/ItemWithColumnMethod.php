<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

class ItemWithColumnMethod extends Item
{
    public function propertiesColumn(): string
    {
        return 'meta';
    }
}
