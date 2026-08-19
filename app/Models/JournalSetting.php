<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Illuminate\Database\Eloquent\Model;

class JournalSetting extends Model
{
    use BelongsToJournal;

    protected $fillable = ['journal_id', 'name', 'locale', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
