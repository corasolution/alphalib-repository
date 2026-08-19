<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreservationDeposit extends Model
{
    protected $fillable = ['publication_id', 'disk', 'path', 'sha256', 'size'];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }
}
