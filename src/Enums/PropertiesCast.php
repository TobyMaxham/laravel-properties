<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Enums;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Casts\ArrayObject;

/**
 * What `$model->properties` returns and how the column is stored.
 */
enum PropertiesCast: string
{
    /** A plain PHP array (default). */
    case Array = 'array';

    /** An `Illuminate\Database\Eloquent\Casts\ArrayObject`, like Laravel's `AsArrayObject`. */
    case Object = 'object';

    /** An `Illuminate\Support\Collection`, like Laravel's `AsCollection`. */
    case Collection = 'collection';

    /** Like `Array`, but the whole column is encrypted. */
    case EncryptedArray = 'encrypted_array';

    /** Like `Object`, but the whole column is encrypted. */
    case EncryptedObject = 'encrypted_object';

    /** Like `Collection`, but the whole column is encrypted. */
    case EncryptedCollection = 'encrypted_collection';

    public function isEncrypted(): bool
    {
        return match ($this) {
            self::EncryptedArray, self::EncryptedObject, self::EncryptedCollection => true,
            default                                                                => false,
        };
    }

    /**
     * Wrap the decoded properties in the type of this cast.
     *
     * @param  array<array-key, mixed>  $properties
     * @return array<array-key, mixed>|ArrayObject<array-key, mixed>|Collection<array-key, mixed>
     */
    public function wrap(array $properties): array|ArrayObject|Collection
    {
        return match ($this) {
            self::Array, self::EncryptedArray           => $properties,
            self::Object, self::EncryptedObject         => new ArrayObject($properties, ArrayObject::ARRAY_AS_PROPS),
            self::Collection, self::EncryptedCollection => new Collection($properties),
        };
    }
}
