<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Note;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Status;
use TobyMaxham\LaravelProperties\Tests\Fixtures\QueryItem;
use TobyMaxham\LaravelProperties\Tests\Fixtures\EncryptedItem;
use TobyMaxham\LaravelProperties\Exceptions\CannotQueryEncryptedPropertyException;

/**
 * Defaults of QueryItem: theme=light, newsletter=false, logins=0, roles=[user]
 *
 * A: everything set
 * B: some values, theme set to the default
 * C: empty object (only defaults)
 * D: column is null (only defaults)
 * E: theme is null, encrypted token, empty tags
 */
beforeEach(function () {
    QueryItem::create(['name' => 'A'])->setProperties([
        'theme'     => 'dark', 'newsletter' => true, 'logins' => 10, 'score' => 2.5, 'status' => Status::Active,
        'joined_at' => '2024-01-10', 'tags' => ['a', 'b'], 'roles' => ['admin'], 'ui' => ['size' => 'lg'],
    ])->save();
    QueryItem::create(['name' => 'B'])->setProperties([
        'theme' => 'light', 'logins' => 9, 'status' => Status::Inactive, 'joined_at' => '2024-03-01', 'tags' => ['b'],
    ])->save();
    QueryItem::create(['name' => 'C'])->clearProperties()->save();
    QueryItem::create(['name' => 'D']);
    QueryItem::create(['name' => 'E'])->setProperties([
        'theme' => null, 'newsletter' => false, 'logins' => 100, 'tags' => [], 'api_token' => 'secret',
    ])->save();
});

/**
 * @param  Builder<QueryItem>  $query
 * @return list<string>
 */
function names(Builder $query): array
{
    return $query->pluck('name')->sort()->values()->all();
}

describe('whereProperty', function () {
    it('compares strings', function () {
        expect(names(QueryItem::whereProperty('theme', 'dark')))->toBe(['A'])
            ->and(names(QueryItem::whereProperty('theme', '=', 'dark')))->toBe(['A'])
            ->and(names(QueryItem::whereProperty('theme', 'like', 'da%')))->toBe(['A'])
            ->and(names(QueryItem::whereProperty('ui.size', 'lg')))->toBe(['A'])
            ->and(names(QueryItem::whereProperty('tags.0', 'b')))->toBe(['B'])
            ->and(names(QueryItem::whereProperty('roles', 'admin')))->toBe([]); // no list comparison, the default list never matches
    });

    it('includes rows without the property when the default matches', function () {
        expect(names(QueryItem::whereProperty('theme', 'light')))->toBe(['B', 'C', 'D'])
            ->and(names(QueryItem::whereProperty('theme', '!=', 'light')))->toBe(['A'])
            ->and(names(QueryItem::whereProperty('theme', '!=', 'dark')))->toBe(['B', 'C', 'D']);
    });

    it('compares booleans', function () {
        expect(names(QueryItem::whereProperty('newsletter', true)))->toBe(['A'])
            ->and(names(QueryItem::whereProperty('newsletter', false)))->toBe(['B', 'C', 'D', 'E'])
            ->and(names(QueryItem::whereProperty('newsletter', '1')))->toBe(['A']); // bool cast
    });

    it('compares numbers as numbers', function () {
        expect(names(QueryItem::whereProperty('logins', '>', 9)))->toBe(['A', 'E'])
            ->and(names(QueryItem::whereProperty('logins', '<', 10)))->toBe(['B', 'C', 'D'])
            ->and(names(QueryItem::whereProperty('logins', '9')))->toBe(['B']) // int cast
            ->and(names(QueryItem::whereProperty('logins', 0)))->toBe(['C', 'D'])
            ->and(names(QueryItem::whereProperty('score', '>=', 2.5)))->toBe(['A']);
    });

    it('converts enums and dates', function () {
        expect(names(QueryItem::whereProperty('status', Status::Active)))->toBe(['A'])
            ->and(names(QueryItem::whereProperty('status', 'inactive')))->toBe(['B'])
            ->and(names(QueryItem::whereProperty('joined_at', '>=', Carbon::parse('2024-02-01 10:00'))))->toBe(['B']);
    });

    it('handles null', function () {
        expect(names(QueryItem::whereProperty('theme', null)))->toBe(['E'])
            ->and(names(QueryItem::whereProperty('theme', '!=', null)))->toBe(['A', 'B', 'C', 'D'])
            ->and(names(QueryItem::wherePropertyNull('score')))->toBe(['B', 'C', 'D', 'E'])
            ->and(names(QueryItem::wherePropertyNotNull('score')))->toBe(['A'])
            ->and(names(QueryItem::wherePropertyNotNull('theme')))->toBe(['A', 'B', 'C', 'D']);
    });

    it('combines conditions with or', function () {
        expect(names(QueryItem::whereProperty('theme', 'dark')->orWhereProperty('logins', 9)))->toBe(['A', 'B'])
            ->and(names(QueryItem::whereProperty('theme', 'dark')->orWhereProperty('logins', '>', 50)))->toBe(['A', 'E'])
            ->and(names(QueryItem::where('name', 'A')->orWhereProperty('theme', 'light')))->toBe(['A', 'B', 'C', 'D']);
    });

    it('works with joins', function () {
        $query = QueryItem::query()
            ->join('items as other', 'other.id', '=', 'items.id')
            ->whereProperty('theme', 'dark')
            ->select('items.*');

        expect(names($query))->toBe(['A']);
    });

    it('rejects invalid operators', function (mixed $operator) {
        QueryItem::whereProperty('theme', $operator, 'dark');
    })->with(['invalid', 'or 1=1', [[]]])->throws(InvalidArgumentException::class);
});

it('matches several properties', function () {
    expect(names(QueryItem::whereProperties(['theme' => 'light', 'status' => Status::Inactive])))->toBe(['B'])
        ->and(names(QueryItem::whereProperties(['theme' => 'light', 'newsletter' => false])))->toBe(['B', 'C', 'D']);
});

describe('wherePropertyIn', function () {
    it('matches values of a list', function () {
        expect(names(QueryItem::wherePropertyIn('theme', ['dark', 'light'])))->toBe(['A', 'B', 'C', 'D'])
            ->and(names(QueryItem::wherePropertyIn('theme', collect(['dark']))))->toBe(['A'])
            ->and(names(QueryItem::wherePropertyIn('logins', [9, '100'])))->toBe(['B', 'E'])
            ->and(names(QueryItem::wherePropertyIn('status', [Status::Active])))->toBe(['A'])
            ->and(names(QueryItem::wherePropertyIn('newsletter', [true])))->toBe(['A'])
            ->and(names(QueryItem::wherePropertyIn('theme', [null, 'dark'])))->toBe(['A', 'E'])
            ->and(names(QueryItem::wherePropertyIn('theme', [])))->toBe([]);
    });

    it('excludes values of a list', function () {
        expect(names(QueryItem::wherePropertyNotIn('theme', ['dark'])))->toBe(['B', 'C', 'D'])
            ->and(names(QueryItem::wherePropertyNotIn('logins', [0, 9])))->toBe(['A', 'E'])
            ->and(names(QueryItem::wherePropertyNotIn('theme', [null, 'dark'])))->toBe(['B', 'C', 'D'])
            ->and(names(QueryItem::wherePropertyNotIn('theme', [])))->toBe(['A', 'B', 'C', 'D', 'E']);
    });
});

describe('wherePropertyBetween', function () {
    it('matches ranges', function () {
        expect(names(QueryItem::wherePropertyBetween('logins', [5, 50])))->toBe(['A', 'B'])
            ->and(names(QueryItem::wherePropertyBetween('logins', [0, 9])))->toBe(['B', 'C', 'D'])
            ->and(names(QueryItem::wherePropertyNotBetween('logins', [5, 50])))->toBe(['C', 'D', 'E'])
            ->and(names(QueryItem::wherePropertyBetween('joined_at', ['2024-01-01', '2024-02-01'])))->toBe(['A']);
    });

    it('needs two values', function () {
        QueryItem::wherePropertyBetween('logins', [1]);
    })->throws(InvalidArgumentException::class, 'exactly two values');
});

describe('whereHasProperty', function () {
    it('checks whether a property exists', function () {
        expect(names(QueryItem::whereHasProperty('tags')))->toBe(['A', 'B', 'E'])
            ->and(names(QueryItem::whereDoesntHaveProperty('tags')))->toBe(['C', 'D'])
            ->and(names(QueryItem::whereHasProperty('ui.size')))->toBe(['A'])
            ->and(names(QueryItem::whereHasProperty('api_token')))->toBe(['E']);
    });

    it('treats properties with a default as existing, like hasProperty()', function () {
        expect(names(QueryItem::whereHasProperty('theme')))->toBe(['A', 'B', 'C', 'D', 'E'])
            ->and(names(QueryItem::whereDoesntHaveProperty('theme')))->toBe([]);
    });
});

describe('wherePropertyContains', function () {
    it('checks lists', function () {
        expect(names(QueryItem::wherePropertyContains('tags', 'b')))->toBe(['A', 'B'])
            ->and(names(QueryItem::wherePropertyContains('tags', ['a', 'b'])))->toBe(['A'])
            ->and(names(QueryItem::wherePropertyDoesntContain('tags', 'a')))->toBe(['B', 'C', 'D', 'E']);
    });

    it('uses the default list', function () {
        expect(names(QueryItem::wherePropertyContains('roles', 'user')))->toBe(['B', 'C', 'D', 'E'])
            ->and(names(QueryItem::wherePropertyDoesntContain('roles', 'user')))->toBe(['A'])
            ->and(names(QueryItem::wherePropertyContains('roles', 'admin')))->toBe(['A']);
    });
});

it('checks the length of lists', function () {
    expect(names(QueryItem::wherePropertyLength('tags', 2)))->toBe(['A'])
        ->and(names(QueryItem::wherePropertyLength('tags', '>', 0)))->toBe(['A', 'B'])
        ->and(names(QueryItem::wherePropertyLength('roles', 1)))->toBe(['A', 'B', 'C', 'D', 'E']);
});

describe('orderByProperty', function () {
    it('orders numbers as numbers and uses the default', function () {
        expect(QueryItem::orderByProperty('logins')->orderBy('name')->pluck('name')->all())->toBe(['C', 'D', 'B', 'A', 'E'])
            ->and(QueryItem::orderByPropertyDesc('logins')->orderBy('name')->pluck('name')->all())->toBe(['E', 'A', 'B', 'C', 'D']);
    });

    it('orders strings', function () {
        $names = QueryItem::wherePropertyNotNull('theme')->orderByProperty('theme', 'desc')->orderBy('name')->pluck('name')->all();

        expect($names)->toBe(['B', 'C', 'D', 'A']);
    });

    it('orders without a default', function () {
        expect(QueryItem::whereHasProperty('joined_at')->orderByProperty('joined_at')->pluck('name')->all())->toBe(['A', 'B']);
    });

    it('rejects invalid directions', function () {
        QueryItem::orderByProperty('logins', 'sideways');
    })->throws(InvalidArgumentException::class, 'The order direction must be "asc" or "desc".');
});

describe('encryption', function () {
    it('does not query encrypted properties', function (Closure $query) {
        expect($query)->toThrow(CannotQueryEncryptedPropertyException::class);
    })->with([
        'where'    => fn () => QueryItem::whereProperty('api_token', 'secret'),
        'in'       => fn () => QueryItem::wherePropertyIn('api_token', ['secret']),
        'order by' => fn () => QueryItem::orderByProperty('api_token'),
        'column'   => fn () => EncryptedItem::whereHasProperty('a'),
    ]);
});

it('works in relations and whereHas()', function () {
    Schema::dropIfExists('notes');
    Schema::create('notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('item_id');
        $table->string('text');
        $table->timestamps();
    });

    $a = QueryItem::where('name', 'A')->firstOrFail();
    $b = QueryItem::where('name', 'B')->firstOrFail();
    Note::create(['item_id' => $a->id, 'text' => 'note of A']);
    Note::create(['item_id' => $b->id, 'text' => 'note of B']);

    expect(Note::whereHas('item', fn (Builder $query) => $query->whereProperty('theme', 'dark'))->pluck('text')->all())->toBe(['note of A'])
        ->and(Note::whereRelation('item', fn (Builder $query) => $query->whereProperty('logins', '<', 10))->pluck('text')->all())->toBe(['note of B'])
        ->and(Note::first()->item()->whereProperty('theme', 'dark')->exists())->toBeTrue();

    Schema::drop('notes');
});
