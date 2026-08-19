<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryMessage extends Model
{
    protected $fillable = ['query_id', 'user_id', 'body'];

    /**
     * Named discussion(), not query() — the latter collides with Eloquent's static
     * Model::query() method.
     */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Query::class, 'query_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
