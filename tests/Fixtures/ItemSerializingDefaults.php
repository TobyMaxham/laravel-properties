<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

class ItemSerializingDefaults extends Item
{
    public const bool SERIALIZE_PROPERTY_DEFAULTS = true;

    /**
     * @return array<string, mixed>
     */
    public function propertyDefaults(): array
    {
        return ['theme' => 'light'];
    }
}
