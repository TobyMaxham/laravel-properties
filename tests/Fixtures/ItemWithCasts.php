<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

use TobyMaxham\LaravelProperties\PropertyCasts\EnumCast;

class ItemWithCasts extends Item
{
    /** @var array<string, mixed> */
    protected array $propertyDefaults = [
        'status'     => Status::Active,
        'newsletter' => false,
    ];

    /**
     * @return array<string, mixed>
     */
    public function propertyCasts(): array
    {
        return [
            'count'           => 'int',
            'ratio'           => 'float',
            'price'           => 'decimal:2',
            'newsletter'      => 'bool',
            'title'           => 'string',
            'tags'            => 'array',
            'list'            => 'collection',
            'birthday'        => 'date',
            'starts_at'       => 'datetime',
            'local_date'      => 'date:d.m.Y',
            'frozen'          => 'immutable_datetime',
            'status'          => Status::class,
            'priority'        => Priority::class,
            'mood'            => EnumCast::lenient(Status::class),
            'address.country' => Status::class,
            'amount'          => MoneyCast::class,
            'amount_usd'      => MoneyCast::class.':USD',
            'amount_chf'      => new MoneyCast('CHF'),
        ];
    }
}
