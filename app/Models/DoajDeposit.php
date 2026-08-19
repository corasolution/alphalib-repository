<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoajDeposit extends Model
{
    protected $fillable = ['publication_id', 'doaj_article_id', 'status', 'attempts', 'last_error', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }
}
