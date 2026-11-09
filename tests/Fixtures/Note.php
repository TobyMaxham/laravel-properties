<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<QueryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(QueryItem::class, 'item_id');
    }
}
