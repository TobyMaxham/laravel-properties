<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

use TobyMaxham\LaravelProperties\Enums\PropertiesCast;

class ObjectItem extends Item
{
    public const PropertiesCast PROPERTIES_CAST = PropertiesCast::Object;
}
