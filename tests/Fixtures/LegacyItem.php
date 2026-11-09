<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\Traits\UseProperties;

/**
 * A model as documented in 1.x. Redeclaring `$propertiesField` was a fatal error in 1.x.
 */
class LegacyItem extends Model
{
    use UseProperties;

    protected $table = 'items';

    protected string $propertiesField = 'meta';
}
