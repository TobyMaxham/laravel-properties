<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use TobyMaxham\LaravelProperties\Casts\AsProperties;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Item;
use TobyMaxham\LaravelProperties\Enums\PropertiesCast;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use TobyMaxham\LaravelProperties\Tests\Fixtures\ObjectItem;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use TobyMaxham\LaravelProperties\Tests\Fixtures\EncryptedItem;
use TobyMaxham\LaravelProperties\Tests\Fixtures\CollectionItem;
use TobyMaxham\LaravelProperties\Exceptions\InvalidAttributeTypeException;

it('stores JSON with unicode, slashes and floats unescaped', function () {
    $item = (new Item)->setProperties(['name' => 'Jürgen', 'url' => 'https://example.com/a', 'price' => 1.0]);
    $item->save();

    expect($item->getRawOriginal('properties'))->toBe('{"name":"Jürgen","url":"https://example.com/a","price":1.0}');
});

it('returns the attribute as array, object or collection', function (string $class, string $type) {
    $item = (new $class)->setProperty('a', 1);

    expect($item->properties)->toBeInstanceOf($type)
        ->and($item->getProperty('a'))->toBe(1);
})->with([
    'object'     => [ObjectItem::class, ArrayObject::class],
    'collection' => [CollectionItem::class, Collection::class],
]);

it('saves changes made directly on an ArrayObject', function () {
    $item = ObjectItem::create();
    $item->properties['theme'] = 'dark';
    $item->properties->size = 'lg';
    $item->save();

    expect(ObjectItem::find($item->id)->getProperties())->toBeProperties(['theme' => 'dark', 'size' => 'lg']);
});

it('encrypts the whole column', function () {
    $item = EncryptedItem::create();
    $item->setProperty('token', 'secret-value')->save();

    $raw = $item->getRawOriginal('secret');

    expect($raw)->not->toContain('secret-value')
        ->and(json_decode((string) decrypt($raw, false), true))->toBe(['token' => 'secret-value'])
        ->and(EncryptedItem::find($item->id)->getProperty('token'))->toBe('secret-value');
});

it('supports all encrypted casts', function (PropertiesCast $cast, string $type) {
    $class = 'EncryptedCast'.$cast->name;
    eval(sprintf(
        'class %s extends %s { public const PROPERTIES_COLUMN = "secret"; public const PROPERTIES_CAST = %s::%s; }',
        $class, Item::class, PropertiesCast::class, $cast->name,
    ));
    $item = new $class;

    $item->setProperty('a', 1)->save();

    expect($cast->isEncrypted())->toBeTrue()
        ->and($item->getRawOriginal('secret'))->not->toContain('"a"')
        ->and($class::find($item->id)->secret)->toBeInstanceOf($type);
})->with([
    'object'     => [PropertiesCast::EncryptedObject, ArrayObject::class],
    'collection' => [PropertiesCast::EncryptedCollection, Collection::class],
]);

it('is not dirty when the database formats the JSON differently', function () {
    $item = Item::create();
    DB::table('items')->where('id', $item->id)->update(['properties' => '{"b": 2, "a": {"y": 1, "x": [1, 2]}}']);
    $item = Item::find($item->id);

    $item->setProperties(['a.x' => [1, 2], 'b' => 2]);

    expect($item->isDirty('properties'))->toBeFalse();

    $item->setProperty('a.x', [2, 1]);

    expect($item->isDirty('properties'))->toBeTrue();
});

it('is not dirty when encrypted data does not change', function () {
    $item = EncryptedItem::create();
    $item->setProperty('a', 1)->save();

    $item->setProperty('a', 1);

    expect($item->isDirty('secret'))->toBeFalse()
        ->and($item->setProperty('a', 2)->isDirty('secret'))->toBeTrue();
});

it('detects changes from and to null', function () {
    $item = Item::create();

    expect($item->setProperty('a', 1)->isDirty('properties'))->toBeTrue();

    $item->save();
    $item->properties = null;

    expect($item->isDirty('properties'))->toBeTrue()
        ->and($item->getProperties())->toBe([]);
});

it('treats invalid JSON as a change', function () {
    $item = Item::create();
    DB::table('items')->where('id', $item->id)->update(['properties' => 'invalid']);
    $item = Item::find($item->id);
    $item->setRawAttributes(['properties' => '{}'] + $item->getAttributes());

    expect($item->isDirty('properties'))->toBeTrue();
})->skip(fn () => 'sqlite' !== DB::getDriverName(), 'Only SQLite stores invalid JSON.');

it('throws for invalid content of the column', function (string $raw, string $message) {
    $item = Item::create();
    DB::table('items')->where('id', $item->id)->update(['properties' => $raw]);

    expect(fn () => Item::find($item->id)->getProperties())->toThrow(InvalidAttributeTypeException::class, $message);
})->with([
    'invalid json' => ['{invalid', 'does not contain valid JSON'],
    'scalar'       => ['"text"', 'must contain a JSON object or array, [string] given'],
])->skip(fn () => 'sqlite' !== DB::getDriverName(), 'Only SQLite stores invalid JSON.');

it('throws when a value can not be stored', function () {
    $item = new Item;
    $item->properties = 'text';
})->throws(InvalidAttributeTypeException::class, 'must contain a JSON object or array, [string] given');

it('accepts arrayable, traversable and stdClass values', function (mixed $value) {
    $item = new Item;
    $item->properties = $value;

    expect($item->getProperties())->toBe(['a' => 1]);
})->with([
    'collection' => fn () => new Collection(['a' => 1]),
    'iterator'   => fn () => new ArrayIterator(['a' => 1]),
    'stdClass'   => fn () => (object) ['a' => 1],
]);

it('keeps the type of an existing Laravel cast', function (string $cast, PropertiesCast $expected) {
    $class = 'LaravelCast'.md5($cast);
    eval(sprintf(
        'class %s extends %s { protected function casts(): array { return ["properties" => %s]; } }',
        $class, Item::class, var_export($cast, true),
    ));
    $item = new $class;

    expect($item->propertiesCast())->toBe($expected)
        ->and($item->getCasts()['properties'])->toBe(AsProperties::using($expected));
})->with([
    'array'                  => ['array', PropertiesCast::Array],
    'json'                   => ['json', PropertiesCast::Array],
    'object'                 => ['object', PropertiesCast::Object],
    'collection'             => ['collection', PropertiesCast::Collection],
    'encrypted:array'        => ['encrypted:array', PropertiesCast::EncryptedArray],
    'encrypted:object'       => ['encrypted:object', PropertiesCast::EncryptedObject],
    'encrypted:collection'   => ['encrypted:collection', PropertiesCast::EncryptedCollection],
    'AsArrayObject'          => [AsArrayObject::class, PropertiesCast::Object],
    'AsCollection'           => [AsCollection::class, PropertiesCast::Collection],
    'AsEncryptedArrayObject' => [AsEncryptedArrayObject::class, PropertiesCast::EncryptedObject],
    'AsEncryptedCollection'  => [AsEncryptedCollection::class, PropertiesCast::EncryptedCollection],
    'AsProperties'           => [AsProperties::class, PropertiesCast::Array],
    'AsProperties with type' => [AsProperties::class.':collection', PropertiesCast::Collection],
]);

it('reads data stored by Laravel encrypted:array', function () {
    $item = EncryptedItem::create();
    DB::table('items')->where('id', $item->id)->update(['secret' => encrypt('{"a":1}', false)]);

    expect(EncryptedItem::find($item->id)->getProperties())->toBe(['a' => 1]);
});

it('keeps a custom cast of the developer', function () {
    $item = new class extends Item
    {
        protected function casts(): array
        {
            return ['properties' => UppercaseKeysCast::class];
        }
    };

    $item->setProperty('a', 1);

    expect($item->getCasts()['properties'])->toBe(UppercaseKeysCast::class)
        ->and($item->getProperties())->toBe(['A' => 1]);
});

it('throws when a custom cast returns no array', function () {
    $item = new class extends Item
    {
        protected function casts(): array
        {
            return ['properties' => 'string'];
        }
    };
    $item->setRawAttributes(['properties' => '{}']);

    $item->getProperties();
})->throws(InvalidAttributeTypeException::class, '[string] given');

it('can be used without the trait', function () {
    $item = new class extends Model
    {
        protected $table = 'items';

        protected function casts(): array
        {
            return ['properties' => AsProperties::using(PropertiesCast::Collection)];
        }
    };
    $item->properties = ['a' => 1];
    $item->save();

    expect($item->properties)->toBeInstanceOf(Collection::class)
        ->and($item->toArray()['properties'])->toBe(['a' => 1])
        ->and($item->getRawOriginal('properties'))->toBe('{"a":1}');
});

it('rejects an invalid cast', function () {
    new AsProperties('invalid');
})->throws(InvalidArgumentException::class, 'Invalid properties cast [invalid]. Allowed: array, object, collection');

it('rejects invalid configuration', function (string $constant, mixed $value, string $message) {
    $class = 'InvalidConfig'.$constant;

    eval(sprintf(
        'class %s extends %s { public const %s = %s; }',
        $class, Item::class, $constant, var_export($value, true),
    ));

    expect(fn () => (new $class)->getProperties())->toThrow(InvalidArgumentException::class, $message);
})->with([
    'empty column' => ['PROPERTIES_COLUMN', '', 'The properties column of [InvalidConfigPROPERTIES_COLUMN] must be a non-empty string.'],
    'invalid cast' => ['PROPERTIES_CAST', 'invalid', 'Invalid properties cast [invalid] on [InvalidConfigPROPERTIES_CAST].'],
    'no defaults'  => ['PROPERTY_DEFAULTS', 'text', 'The property defaults of [InvalidConfigPROPERTY_DEFAULTS] must be an array.'],
]);

class UppercaseKeysCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return array_change_key_case(json_decode($value ?? '{}', true), CASE_UPPER);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode($value);
    }
}

// Eloquent calls the initializers in the order of ReflectionClass::getMethods(), which differs between environments.
it('keeps the cast when casts() is merged after the trait was initialized', function (string $cast, PropertiesCast $expected) {
    $class = 'LateCasts'.md5($cast);
    eval(sprintf(
        'class %s extends %s { protected function casts(): array { return ["properties" => %s]; } }',
        $class, Item::class, var_export($cast, true),
    ));
    $item = new $class;

    // Simulate the other order: initializeHasProperties() first, then initializeHasAttributes().
    $item->setRawAttributes([]);
    (fn () => $this->initializeHasProperties())->call($item);
    (fn () => $this->initializeHasAttributes())->call($item);

    expect($item->getCasts()['properties'])->toBe(AsProperties::using($expected));
})->with([
    'array'         => ['array', PropertiesCast::Array],
    'AsArrayObject' => [AsArrayObject::class, PropertiesCast::Object],
]);
