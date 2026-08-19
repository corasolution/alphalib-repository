<?php

namespace Alpha\Journal\Models\Concerns;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Scopes\JournalScope;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToJournal
{
    public static function bootBelongsToJournal(): void
    {
        static::addGlobalScope(new JournalScope);

        static::creating(function ($model) {
            if ($model->journal_id === null && app(JournalContext::class)->has()) {
                $model->journal_id = app(JournalContext::class)->get()->id;
            }
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
