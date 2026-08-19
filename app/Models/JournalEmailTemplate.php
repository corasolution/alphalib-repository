<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Illuminate\Database\Eloquent\Model;

class JournalEmailTemplate extends Model
{
    use BelongsToJournal;

    protected $fillable = ['journal_id', 'key', 'locale', 'subject', 'body', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
