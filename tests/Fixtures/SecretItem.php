<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

class SecretItem extends Item
{
    /** @var array<string, mixed> */
    protected array $propertyDefaults = ['theme' => 'light'];

    /**
     * @return array<string, mixed>
     */
    public function propertyCasts(): array
    {
        return [
            'api_token'   => 'encrypted',
            'pin'         => 'encrypted:int',
            'expires_at'  => 'encrypted:datetime',
            'level'       => 'encrypted:'.Status::class,
            'creds.token' => 'encrypted',
        ];
    }
}
