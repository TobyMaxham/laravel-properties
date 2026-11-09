<?php

declare(strict_types=1);
use TobyMaxham\LaravelProperties\Exceptions\PropertiesException;

arch('strict types')
    ->expect('TobyMaxham\LaravelProperties')
    ->toUseStrictTypes();

arch('no debugging')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('exceptions extend the base exception')
    ->expect('TobyMaxham\LaravelProperties\Exceptions')
    ->toExtend(PropertiesException::class)
    ->ignoring(PropertiesException::class);
