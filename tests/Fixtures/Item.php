<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\HasProperties;
use TobyMaxham\LaravelProperties\Contracts\HasProperties as HasPropertiesContract;

class Item extends Model implements HasPropertiesContract
{
    use HasProperties;

    protected $table = 'items';

    protected $guarded = [];
}
