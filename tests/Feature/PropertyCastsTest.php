<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Item;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Size;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Status;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Priority;
use TobyMaxham\LaravelProperties\Tests\Fixtures\ItemWithCasts;
use TobyMaxham\LaravelProperties\Exceptions\InvalidPropertyValueException;

function storedItem(array $properties): ItemWithCasts
{
    $item = ItemWithCasts::create();
    DB::table('items')->where('id', $item->id)->update(['properties' => json_encode($properties)]);

    return ItemWithCasts::find($item->id);
}

describe('primitives', function () {
    it('converts values when writing', function (string $key, mixed $value, mixed $stored) {
        $item = (new ItemWithCasts)->setProperty($key, $value);

        expect($item->getStoredProperties()[$key])->toBe($stored);
    })->with([
        'int from string'      => ['count', '5', 5],
        'int from float'       => ['count', 5.0, 5],
        'float from string'    => ['ratio', '1.5', 1.5],
        'float from int'       => ['ratio', 2, 2.0],
        'decimal'              => ['price', 1.235, '1.24'],
        'decimal from string'  => ['price', '3', '3.00'],
        'bool from string'     => ['newsletter', 'yes', true],
        'bool from 0'          => ['newsletter', '0', false],
        'bool from int'        => ['newsletter', 1, true],
        'string from int'      => ['title', 5, '5'],
        'string from enum'     => ['title', Status::Active, 'active'],
        'string from object'   => ['title', str('text'), 'text'],
        'array from arrayable' => ['tags', collect(['a']), ['a']],
        'collection'           => ['list', collect(['a']), ['a']],
        'null stays null'      => ['count', null, null],
    ]);

    it('rejects values that do not fit', function (string $key, mixed $value, string $message) {
        expect(fn () => (new ItemWithCasts)->setProperty($key, $value))
            ->toThrow(InvalidPropertyValueException::class, $message);
    })->with([
        'int'     => ['count', 'abc', 'The property [count] of ['.ItemWithCasts::class.'] must be an integer, [string] given.'],
        'int 1.5' => ['count', 1.5, 'must be an integer, [float] given'],
        'float'   => ['ratio', 'abc', 'must be a number, [string] given'],
        'decimal' => ['price', 'abc', 'must be a number'],
        'bool'    => ['newsletter', 'maybe', 'must be a boolean'],
        'bool []' => ['newsletter', [], 'must be a boolean, [array] given'],
        'string'  => ['title', [], 'must be a string'],
        'array'   => ['tags', 'text', 'must be an array'],
    ]);

    it('converts values when reading and never throws', function () {
        $item = storedItem(['count' => '7', 'ratio' => '0.5', 'price' => 2, 'newsletter' => 'off', 'title' => 12, 'tags' => 'text', 'list' => ['a']]);

        expect($item->getProperty('count'))->toBe(7)
            ->and($item->getProperty('ratio'))->toBe(0.5)
            ->and($item->getProperty('price'))->toBe('2.00')
            ->and($item->getProperty('newsletter'))->toBeFalse()
            ->and($item->getProperty('title'))->toBe('12')
            ->and($item->getProperty('tags'))->toBe('text')
            ->and($item->getProperty('list'))->toBeInstanceOf(Collection::class)->toArray()->toBe(['a']);
    });

    it('keeps values when reading that can not be converted', function () {
        $item = storedItem(['count' => ['x'], 'ratio' => ['x'], 'price' => 'x', 'newsletter' => ['x'], 'title' => ['x'], 'list' => 'x']);

        expect($item->getProperty('count'))->toBe(['x'])
            ->and($item->getProperty('ratio'))->toBe(['x'])
            ->and($item->getProperty('price'))->toBe('x')
            ->and($item->getProperty('newsletter'))->toBe(['x'])
            ->and($item->getProperty('title'))->toBe(['x'])
            ->and($item->getProperty('list')->all())->toBe(['x'])
            ->and(storedItem(['newsletter' => 'x'])->getProperty('newsletter'))->toBeTrue();
    });
});

describe('dates', function () {
    beforeEach(fn () => Carbon::setTestNow('2024-05-01 12:30:45'));

    it('stores dates and date times', function () {
        $item = (new ItemWithCasts)->setProperties([
            'birthday'   => Carbon::parse('1990-02-03 10:00'),
            'starts_at'  => '2024-06-01 08:15:00',
            'local_date' => Carbon::parse('2024-12-24'),
            'frozen'     => now()->timestamp,
        ]);

        expect($item->getStoredProperties())->toBe([
            'birthday'   => '1990-02-03',
            'starts_at'  => '2024-06-01 08:15:00',
            'local_date' => '24.12.2024',
            'frozen'     => '2024-05-01 12:30:45',
        ]);
    });

    it('reads dates as Carbon', function () {
        $item = storedItem(['birthday' => '1990-02-03', 'starts_at' => '2024-06-01 08:15:00', 'local_date' => '24.12.2024', 'frozen' => '2024-05-01 12:30:45']);

        expect($item->getProperty('birthday'))->toBeInstanceOf(Carbon::class)
            ->and($item->getProperty('birthday')->toDateTimeString())->toBe('1990-02-03 00:00:00')
            ->and($item->getProperty('starts_at')->toDateTimeString())->toBe('2024-06-01 08:15:00')
            ->and($item->getProperty('local_date')->toDateString())->toBe('2024-12-24')
            ->and($item->getProperty('frozen'))->toBeInstanceOf(CarbonImmutable::class);
    });

    it('reads other formats', function () {
        $item = storedItem(['local_date' => '2024-12-24', 'starts_at' => '2024-06-01T08:15:00+00:00']);

        expect($item->getProperty('local_date')->toDateString())->toBe('2024-12-24')
            ->and($item->getProperty('starts_at')->toDateTimeString())->toBe('2024-06-01 08:15:00');
    });

    it('rejects invalid dates', function (mixed $value) {
        expect(fn () => (new ItemWithCasts)->setProperty('birthday', $value))
            ->toThrow(InvalidPropertyValueException::class, 'must be a date');
    })->with(['not a date', '', [[]], true]);

    it('throws when reading an invalid date', function () {
        storedItem(['birthday' => 'not a date'])->getProperty('birthday');
    })->throws(InvalidPropertyValueException::class, 'must be a date, [string] given');
});

describe('enums', function () {
    it('stores the value of an enum', function () {
        $item = (new ItemWithCasts)->setProperties(['status' => Status::Inactive, 'priority' => '2']);

        expect($item->getStoredProperties())->toBe(['status' => 'inactive', 'priority' => 2])
            ->and($item->getProperty('status'))->toBe(Status::Inactive)
            ->and($item->getProperty('priority'))->toBe(Priority::High);
    });

    it('validates enum values when writing', function (string $key, mixed $value, string $message) {
        expect(fn () => (new ItemWithCasts)->setProperty($key, $value))
            ->toThrow(InvalidPropertyValueException::class, $message);
    })->with([
        'unknown string' => ['status', 'deleted', 'The property [status] of ['.ItemWithCasts::class."] is invalid: 'deleted' is not a valid value of [".Status::class.']'],
        'unknown int'    => ['priority', 3, '3 is not a valid value'],
        'wrong type'     => ['priority', 'high', "'high' is not a valid value"],
        'other enum'     => ['status', Priority::Low, '['.Priority::class.'] is not a valid value'],
        'string for int' => ['status', 1, '1 is not a valid value'],
    ]);

    it('throws when reading an unknown value', function () {
        storedItem(['status' => 'deleted'])->getProperty('status');
    })->throws(InvalidPropertyValueException::class, "'deleted' is not a valid value");

    it('returns null for unknown values of a lenient enum', function () {
        expect(storedItem(['mood' => 'deleted'])->getProperty('mood'))->toBeNull()
            ->and((new ItemWithCasts)->setProperty('mood', 'deleted')->getStoredProperties())->toBe(['mood' => null])
            ->and((new ItemWithCasts)->setProperty('mood', 'active')->getProperty('mood'))->toBe(Status::Active);
    });

    it('uses enums as defaults', function () {
        $item = (new ItemWithCasts)->forceFill(['properties' => null]);

        expect($item->getProperty('status'))->toBe(Status::Active)
            ->and($item->withPropertyDefaults()->toArray()['properties'])->toBe(['status' => 'active', 'newsletter' => false]);
    });

    it('does not break other properties when one value is invalid', function () {
        $item = storedItem(['status' => 'deleted', 'count' => 1]);

        expect($item->getProperty('count'))->toBe(1)
            ->and($item->hasProperty('status'))->toBeTrue()
            ->and($item->setProperty('count', 2)->getStoredProperties())->toBeProperties(['status' => 'deleted', 'count' => 2]);
    });

    it('rejects enums without a value', function () {
        $class = 'UnitEnumCast';
        eval(sprintf('class %s extends %s { public const PROPERTY_CASTS = ["size" => %s::class]; }', $class, Item::class, Size::class));

        (new $class)->getProperty('size');
    })->throws(InvalidArgumentException::class, 'The enum ['.Size::class.'] of the property [size] on [UnitEnumCast] must be a backed enum to be stored.');
});

describe('nested keys', function () {
    it('casts nested keys when reading and writing', function () {
        $item = (new ItemWithCasts)->setProperty('address', ['country' => 'active', 'city' => 'Berlin']);

        expect($item->getStoredProperties()['address'])->toBe(['country' => 'active', 'city' => 'Berlin'])
            ->and($item->getProperty('address'))->toBe(['country' => Status::Active, 'city' => 'Berlin'])
            ->and($item->getProperty('address.country'))->toBe(Status::Active)
            ->and($item->getProperties()['address']['country'])->toBe(Status::Active);
    });

    it('validates nested keys', function () {
        (new ItemWithCasts)->setProperty('address.country', 'deleted');
    })->throws(InvalidPropertyValueException::class, 'The property [address.country]');
});

describe('custom casts', function () {
    it('uses custom casts with arguments and instances', function () {
        $item = (new ItemWithCasts)->setProperties(['amount' => '12.34', 'amount_usd' => 1, 'amount_chf' => 0.5]);

        expect($item->getStoredProperties())->toBe(['amount' => 1234, 'amount_usd' => 100, 'amount_chf' => 50])
            ->and($item->getProperty('amount'))->toBe('12.34 EUR')
            ->and($item->getProperty('amount_usd'))->toBe('1.00 USD')
            ->and($item->getProperty('amount_chf'))->toBe('0.50 CHF');
    });
});

describe('methods', function () {
    it('casts all properties in getProperties() and replaceProperties()', function () {
        $item = (new ItemWithCasts)->replaceProperties(['count' => '1', 'status' => Status::Inactive]);

        expect($item->getStoredProperties())->toBe(['count' => 1, 'status' => 'inactive'])
            ->and($item->getProperties())->toBe(['status' => Status::Inactive, 'newsletter' => false, 'count' => 1]);
    });

    it('does not cast the default argument', function () {
        expect((new ItemWithCasts)->getProperty('count', '5'))->toBe('5');
    });

    it('uses the casts in increment, toggle and push', function () {
        $item = storedItem(['count' => '1', 'newsletter' => 'yes', 'tags' => ['a']]);

        $item->incrementProperty('count')->toggleProperty('newsletter')->pushProperty('tags', Status::Active);

        expect($item->getStoredProperties())->toBeProperties(['count' => 2, 'newsletter' => false, 'tags' => ['a', 'active']]);
    });

    it('converts values for the storage', function () {
        $props = (new ItemWithCasts)->props();

        expect($props->toStorage('status', Status::Active))->toBe('active')
            ->and($props->toStorage('unknown', Status::Active))->toBe(Status::Active)
            ->and($props->casts())->toHaveKey('status');
    });

    it('stores enums and dates without a cast as JSON', function () {
        $item = Item::create();
        $item->setProperties(['status' => Status::Active, 'date' => Carbon::parse('2024-01-01 00:00:00', 'UTC')])->save();

        expect(Item::find($item->id)->getProperties())->toBeProperties(['status' => 'active', 'date' => '2024-01-01T00:00:00.000000Z']);
    });
});

describe('configuration', function () {
    it('rejects invalid casts', function (mixed $cast, string $message) {
        $class = 'InvalidCast'.md5(serialize($cast));
        eval(sprintf('class %s extends %s { public const PROPERTY_CASTS = %s; }', $class, Item::class, var_export($cast, true)));

        expect(fn () => (new $class)->getProperty('a'))->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'unknown'       => [['a' => 'unknown'], 'Unknown cast [unknown] for the property [a] on [InvalidCast'],
        'no string'     => [['a' => 5], 'Unknown cast [int] for the property [a]'],
        'empty'         => [['a' => ''], 'Unknown cast [string] for the property [a]'],
        'no digits'     => [['a' => 'decimal'], 'The decimal property cast needs the number of digits'],
        'not an array'  => ['int', 'must be an array'],
        'no enum class' => [['a' => Item::class], 'Unknown cast ['.Item::class.']'],
    ]);
});
