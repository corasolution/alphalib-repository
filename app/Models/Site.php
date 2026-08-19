<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = ['title', 'primary_locale', 'installed_locales', 'redirect_journal_id', 'settings'];

    protected function casts(): array
    {
        return [
            'installed_locales' => 'array',
            'settings' => 'array',
        ];
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function redirectJournal(): ?Journal
    {
        return $this->redirect_journal_id ? Journal::find($this->redirect_journal_id) : null;
    }
}
