<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use TobyMaxham\LaravelProperties\Properties;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Item;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Status;
use TobyMaxham\LaravelProperties\Tests\Fixtures\QueryItem;
use TobyMaxham\LaravelProperties\Tests\Fixtures\SecretItem;
use TobyMaxham\LaravelProperties\Exceptions\PropertiesException;

function rawProperties(int $id): ?array
{
    $value = DB::table('items')->where('id', $id)->value('properties');

    return null === $value ? null : json_decode($value, true);
}

function insertItem(?array $properties, array $columns = []): int
{
    return (int) DB::table('items')->insertGetId([
        'properties' => null === $properties ? null : json_encode($properties),
        ...$columns,
    ]);
}

describe('table', function () {
    it('renames, forgets and sets properties', function () {
        $a = insertItem(['theme' => 'dark', 'legacy' => 1]);
        $b = insertItem(['newsletter' => true]);
        $c = insertItem(null);

        $changed = Properties::table('items')
            ->rename('theme', 'ui.theme')
            ->forget('legacy')
            ->set('newsletter', false, onlyIfMissing: true)
            ->set('version', 2)
            ->run(chunkSize: 2);

        expect($changed)->toBe(3)
            ->and(rawProperties($a))->toBeProperties(['ui' => ['theme' => 'dark'], 'newsletter' => false, 'version' => 2])
            ->and(rawProperties($b))->toBeProperties(['newsletter' => true, 'version' => 2])
            ->and(rawProperties($c))->toBeProperties(['newsletter' => false, 'version' => 2]);
    });

    it('only updates changed rows', function () {
        insertItem(['a' => 1]);
        insertItem(['b' => 1]);

        expect(Properties::table('items')->rename('a', 'c')->run())->toBe(1)
            ->and(Properties::table('items')->run())->toBe(0);
    });

    it('transforms values and sets values with a closure', function () {
        $id = insertItem(['price' => 1250, 'name' => 'x']);

        Properties::table('items')
            ->transform('price', fn (int $cents) => $cents / 100)
            ->transform('missing', fn () => 'never')
            ->set('label', fn (array $properties, object $row) => $properties['name'].'-'.$row->id)
            ->run();

        expect(rawProperties($id))->toBeProperties(['price' => 12.5, 'name' => 'x', 'label' => 'x-'.$id]);
    });

    it('moves columns into properties and back', function () {
        $id = insertItem(['a' => 1], ['name' => 'Bob']);
        $empty = insertItem(null);

        Properties::table('items')->moveIn(['name' => 'user.name'])->run();

        expect(rawProperties($id))->toBeProperties(['a' => 1, 'user' => ['name' => 'Bob']])
            ->and(rawProperties($empty))->toBeNull(); // null is not copied, nothing changed

        DB::table('items')->update(['name' => null]);
        Properties::table('items')->moveOut(['user.name' => 'name', 'a' => 'name'])->run();

        expect(DB::table('items')->where('id', $id)->value('name'))->toBe('1')
            ->and(rawProperties($id))->toBeProperties(['user' => []]);
    });

    it('moves lists into columns as JSON', function () {
        $id = insertItem(['tags' => ['a']]);

        Properties::table('items')->moveOut(['tags' => 'name'])->run();

        expect(json_decode(DB::table('items')->where('id', $id)->value('name'), true))->toBe(['a']);
    });

    it('limits the rows and uses another column', function () {
        $a = insertItem(null, ['name' => 'a', 'settings' => json_encode(['x' => 1])]);
        $b = insertItem(null, ['name' => 'b', 'settings' => json_encode(['x' => 1])]);

        Properties::table('items', column: 'settings')->where(fn ($query) => $query->where('name', 'a'))->set('x', 2)->run();

        expect(json_decode(DB::table('items')->where('id', $a)->value('settings'), true))->toBe(['x' => 2])
            ->and(json_decode(DB::table('items')->where('id', $b)->value('settings'), true))->toBe(['x' => 1]);
    });

    it('throws for invalid JSON', function (string $raw, string $message) {
        insertItem(null);
        DB::table('items')->update(['properties' => $raw]);

        expect(fn () => Properties::table('items')->set('a', 1)->run())->toThrow(PropertiesException::class, $message);
    })->with([
        'invalid' => ['{invalid', 'The column [items.properties] of the row [1] does not contain valid JSON'],
        'scalar'  => ['"text"', 'must contain a JSON object or array'],
    ])->skip(fn () => 'sqlite' !== DB::getDriverName(), 'Only SQLite stores invalid JSON.');
});

describe('model', function () {
    it('uses the casts and the encryption of the model', function () {
        $item = SecretItem::create();
        $item->setProperty('pin', 1)->save();

        Properties::for(SecretItem::class)->set('api_token', 'token')->transform('pin', fn () => '2')->run();

        $item = SecretItem::find($item->id);

        expect($item->getProperty('api_token'))->toBe('token')
            ->and($item->getProperty('pin'))->toBe(2)
            ->and(rawProperties($item->id)['api_token'])->not->toBe('token');
    });

    it('converts values with the property casts', function () {
        $item = QueryItem::create();

        Properties::for(QueryItem::class)->set('status', Status::Inactive)->set('logins', '5')->run();

        expect(rawProperties($item->id))->toBeProperties(['status' => 'inactive', 'logins' => 5]);
    });

    it('moves model attributes and does not touch the timestamps', function () {
        $item = Item::create(['name' => 'Bob']);
        DB::table('items')->update(['updated_at' => '2000-01-01 00:00:00']);

        Properties::for(Item::class)->moveIn(['name' => 'user.name'])->run();
        Properties::for(Item::class)->moveOut(['user.name' => 'name'])->run();

        expect(DB::table('items')->where('id', $item->id)->value('updated_at'))->toBe('2000-01-01 00:00:00')
            ->and(Item::find($item->id)->name)->toBe('Bob')
            ->and(rawProperties($item->id))->toBeProperties(['user' => []]);
    });

    it('needs a model with properties', function () {
        Properties::for(stdClass::class);
    })->throws(InvalidArgumentException::class, 'The model [stdClass] must use the HasProperties trait.');
});
