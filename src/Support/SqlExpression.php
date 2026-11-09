<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Support;

use Illuminate\Database\Grammar;
use Illuminate\Contracts\Database\Query\Expression;

/**
 * Raw SQL built by `PropertyQuery`. Only contains column expressions wrapped by the grammar,
 * whitelisted operators and `?` placeholders. Values are always bound, never part of the SQL.
 *
 * @internal
 */
final readonly class SqlExpression implements Expression
{
    public function __construct(private string $sql) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}
