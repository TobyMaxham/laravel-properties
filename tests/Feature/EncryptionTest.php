<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Encryption\Encrypter;
use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Item;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Status;
use TobyMaxham\LaravelProperties\Tests\Fixtures\SecretItem;
use TobyMaxham\LaravelProperties\PropertyCasts\EncryptedCast;
use TobyMaxham\LaravelProperties\PropertyCasts\PrimitiveCast;
use TobyMaxham\LaravelProperties\Tests\Fixtures\EncryptedItem;
use TobyMaxham\LaravelProperties\Exceptions\InvalidPropertyValueException;
use TobyMaxham\LaravelProperties\Exceptions\CannotQueryEncryptedPropertyException;

afterEach(fn () => Model::encryptUsing(null));

function rawSecretItem(array $properties): SecretItem
{
    $item = SecretItem::create();
    DB::table('items')->where('id', $item->id)->update(['properties' => json_encode($properties)]);

    return SecretItem::find($item->id);
}

it('stores encrypted values and reads them decrypted', function () {
    $item = SecretItem::create();
    $item->setProperties(['api_token' => 'secret-token', 'creds' => ['token' => ['a' => 1.0], 'user' => 'bob'], 'theme' => 'dark'])->save();

    $raw = json_decode($item->getRawOriginal('properties'), true);
    $item = SecretItem::find($item->id);

    expect($item->getRawOriginal('properties'))->not->toContain('secret-token')
        ->and($raw['theme'])->toBe('dark')
        ->and($raw['creds']['user'])->toBe('bob')
        ->and(decrypt($raw['api_token'], false))->toBe('"secret-token"')
        ->and($item->getProperty('api_token'))->toBe('secret-token')
        ->and($item->getProperty('creds.token'))->toBe(['a' => 1.0])
        ->and($item->getProperties()['creds'])->toBeProperties(['token' => ['a' => 1.0], 'user' => 'bob']);
});

it('applies the cast of the decrypted value', function () {
    $item = (new SecretItem)->setProperties(['pin' => '1234', 'expires_at' => '2030-01-02 03:04:05', 'level' => Status::Active]);

    expect($item->getProperty('pin'))->toBe(1234)
        ->and($item->getProperty('expires_at'))->toBeInstanceOf(Carbon::class)
        ->and($item->getProperty('expires_at')->toDateTimeString())->toBe('2030-01-02 03:04:05')
        ->and($item->getProperty('level'))->toBe(Status::Active)
        ->and(decrypt($item->getStoredProperties()['level'], false))->toBe('"active"');
});

it('validates the value before it is encrypted', function () {
    (new SecretItem)->setProperty('pin', 'abc');
})->throws(InvalidPropertyValueException::class, 'The property [pin] of ['.SecretItem::class.'] must be an integer');

it('does not encrypt null', function () {
    $item = (new SecretItem)->setProperty('api_token', null);

    expect($item->getStoredProperties())->toBe(['api_token' => null])
        ->and($item->getProperty('api_token'))->toBeNull();
});

it('keeps the encryption of an unchanged value', function () {
    $item = SecretItem::create();
    $item->setProperties(['api_token' => 'token', 'creds.token' => ['b' => 1, 'a' => 2]])->save();
    $raw = $item->getRawOriginal('properties');

    $item->setProperties(['api_token' => 'token', 'creds.token' => ['a' => 2, 'b' => 1]]);

    expect($item->isDirty('properties'))->toBeFalse()
        ->and($item->getAttributes()['properties'])->toBe($raw)
        ->and($item->setProperty('api_token', 'other')->isDirty('properties'))->toBeTrue();
});

it('works with increment and other helpers', function () {
    $item = (new SecretItem)->setProperty('pin', 1)->incrementProperty('pin', 2);

    expect($item->getProperty('pin'))->toBe(3)
        ->and($item->hasProperty('pin'))->toBeTrue();
});

it('uses the encrypter of the model and supports key rotation', function () {
    $old = Encrypter::generateKey('aes-256-cbc');
    Model::encryptUsing(new Encrypter($old, 'aes-256-cbc'));

    $item = SecretItem::create();
    $item->setProperty('api_token', 'token')->save();

    Model::encryptUsing(new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc'));

    expect(fn () => SecretItem::find($item->id)->getProperty('api_token'))
        ->toThrow(InvalidPropertyValueException::class, 'The property [api_token] of ['.SecretItem::class.'] is invalid: the value can not be decrypted.');

    Model::encryptUsing((new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc'))->previousKeys([$old]));

    expect(SecretItem::find($item->id)->getProperty('api_token'))->toBe('token');
});

it('throws for values that are not encrypted', function (mixed $value, string $message) {
    expect(fn () => rawSecretItem(['api_token' => $value])->getProperty('api_token'))
        ->toThrow(InvalidPropertyValueException::class, $message);
})->with([
    'plain text' => ['token', 'the value can not be decrypted'],
    'number'     => [5, 'the value is not encrypted'],
]);

it('hides encrypted properties in toArray and JSON', function () {
    $item = (new SecretItem)->setProperties(['api_token' => 'token', 'pin' => 1, 'creds' => ['token' => 'x', 'user' => 'bob'], 'theme' => 'dark']);

    expect($item->toArray()['properties'])->toBe(['creds' => ['user' => 'bob'], 'theme' => 'dark'])
        ->and($item->toJson())->not->toContain('api_token')
        ->and($item->withPropertyDefaults()->toArray()['properties'])->toBe(['theme' => 'dark', 'creds' => ['user' => 'bob']]);
});

it('lists the encrypted properties', function () {
    expect((new SecretItem)->props()->encrypted())->toBe(['api_token', 'pin', 'expires_at', 'level', 'creds.token'])
        ->and((new Item)->props()->encrypted())->toBe([]);
});

it('does not allow defaults for encrypted properties', function () {
    $class = 'EncryptedDefault';
    eval(sprintf(
        'class %s extends %s { public const PROPERTY_CASTS = ["creds.token" => "encrypted"]; public const PROPERTY_DEFAULTS = ["creds" => ["token" => "x"]]; }',
        $class, Item::class,
    ));

    (new $class)->getProperty('creds');
})->throws(InvalidArgumentException::class, 'The encrypted property [creds.token] of [EncryptedDefault] can not have a default');

describe('queries', function () {
    it('allows queries on plain properties', function () {
        $props = (new SecretItem)->props();

        $props->ensureQueryable('theme');

        expect($props->isQueryable('theme'))->toBeTrue()
            ->and($props->isQueryable('creds.user'))->toBeTrue()
            ->and($props->isQueryable('pinned'))->toBeTrue();
    });

    it('does not allow queries on encrypted properties', function (string $key, string $encrypted) {
        expect(fn () => (new SecretItem)->props()->ensureQueryable($key))->toThrow(
            CannotQueryEncryptedPropertyException::class,
            "The property [$key] of [".SecretItem::class."] can not be queried, because [$encrypted] is encrypted.",
        );
    })->with([
        'encrypted'        => ['api_token', 'api_token'],
        'inside encrypted' => ['creds.token.value', 'creds.token'],
        'parent encrypted' => ['creds', 'creds.token'],
    ]);

    it('allows checking the existence of encrypted properties', function () {
        expect((new SecretItem)->props()->isQueryable('api_token', keyOnly: true))->toBeTrue()
            ->and((new SecretItem)->props()->isQueryable('api_token'))->toBeFalse();
    });

    it('does not allow any query on an encrypted column', function () {
        expect((new EncryptedItem)->props()->isQueryable('anything', keyOnly: true))->toBeFalse()
            ->and(fn () => (new EncryptedItem)->props()->ensureQueryable('a'))->toThrow(
                CannotQueryEncryptedPropertyException::class,
                'The properties of ['.EncryptedItem::class.'] can not be queried, because the column [secret] is encrypted.',
            );
    });
});

describe('cast', function () {
    it('compares stored values', function () {
        $cast = new EncryptedCast(new PrimitiveCast('int'));
        $model = new Item;
        $stored = $cast->set('5', 'a', $model);

        expect($cast->inner())->toBeInstanceOf(PrimitiveCast::class)
            ->and($cast->contains($stored, 5, 'a', $model))->toBeTrue()
            ->and($cast->contains($stored, '5', 'a', $model))->toBeTrue()
            ->and($cast->contains($stored, 6, 'a', $model))->toBeFalse()
            ->and($cast->contains(null, null, 'a', $model))->toBeTrue()
            ->and($cast->contains(null, 5, 'a', $model))->toBeFalse()
            ->and($cast->contains('invalid', 5, 'a', $model))->toBeFalse()
            ->and($cast->contains($stored, 'abc', 'a', $model))->toBeFalse();
    });

    it('encrypts with a random IV', function () {
        $cast = new EncryptedCast;

        expect($cast->set('a', 'a', new Item))->not->toBe($cast->set('a', 'a', new Item))
            ->and(Str::isJson(base64_decode((string) $cast->set('a', 'a', new Item))))->toBeTrue();
    });
});
