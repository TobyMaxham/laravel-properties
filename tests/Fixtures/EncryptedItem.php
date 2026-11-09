<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

use TobyMaxham\LaravelProperties\Enums\PropertiesCast;

class EncryptedItem extends Item
{
    public const string PROPERTIES_COLUMN = 'secret';

    public function propertiesCast(): PropertiesCast
    {
        return PropertiesCast::EncryptedArray;
    }
}
