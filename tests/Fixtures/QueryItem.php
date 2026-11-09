<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

class QueryItem extends Item
{
    /** @var array<string, mixed> */
    protected array $propertyDefaults = [
        'theme'      => 'light',
        'newsletter' => false,
        'logins'     => 0,
        'roles'      => ['user'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function propertyCasts(): array
    {
        return [
            'newsletter' => 'bool',
            'logins'     => 'int',
            'score'      => 'float',
            'status'     => Status::class,
            'joined_at'  => 'date',
            'tags'       => 'array',
            'api_token'  => 'encrypted',
        ];
    }
}
