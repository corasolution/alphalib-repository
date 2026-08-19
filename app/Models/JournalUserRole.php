<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalUserRole extends Model
{
    use BelongsToJournal;

    protected $fillable = ['journal_id', 'user_id', 'role'];

    protected function casts(): array
    {
        return [
            'role' => JournalRole::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
