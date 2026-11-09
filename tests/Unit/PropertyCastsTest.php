<?php

declare(strict_types=1);

use TobyMaxham\LaravelProperties\Tests\Fixtures\Item;
use TobyMaxham\LaravelProperties\Tests\Fixtures\Status;
use TobyMaxham\LaravelProperties\PropertyCasts\DateCast;
use TobyMaxham\LaravelProperties\PropertyCasts\EnumCast;
use TobyMaxham\LaravelProperties\Support\PropertyCaster;
use TobyMaxham\LaravelProperties\PropertyCasts\PrimitiveCast;

it('rejects unknown types', function (Closure $create, string $message) {
    expect($create)->toThrow(InvalidArgumentException::class, $message);
})->with([
    'primitive' => [fn () => new PrimitiveCast('date'), 'Unknown primitive property cast [date].'],
    'date'      => [fn () => new DateCast('int'), 'Unknown date property cast [int].'],
    'enum'      => [fn () => new EnumCast(Item::class), 'The property cast ['.Item::class.'] must be a backed enum.'],
]);

it('keeps null in primitive casts', function () {
    expect((new PrimitiveCast('int'))->get(null, 'a', new Item))->toBeNull();
});

it('keeps null in date casts', function () {
    $cast = new DateCast('datetime');

    expect($cast->get(null, 'a', new Item))->toBeNull()
        ->and($cast->set(null, 'a', new Item))->toBeNull()
        ->and($cast->storageFormat())->toBe('Y-m-d H:i:s')
        ->and((new DateCast('date'))->storageFormat())->toBe('Y-m-d')
        ->and((new DateCast('date', 'd.m.Y'))->storageFormat())->toBe('d.m.Y');
});

it('finds the cast of a property', function () {
    $caster = new PropertyCaster(new Item, ['status' => Status::class]);

    expect($caster->castFor('status'))->toBeInstanceOf(EnumCast::class)
        ->and($caster->castFor('other'))->toBeNull()
        ->and($caster->isEmpty())->toBeFalse()
        ->and((new PropertyCaster(new Item, []))->isEmpty())->toBeTrue();
});
