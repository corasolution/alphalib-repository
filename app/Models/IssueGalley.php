<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueGalley extends Model
{
    protected $fillable = [
        'issue_id', 'label', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'sort_order',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
