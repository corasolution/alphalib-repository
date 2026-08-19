<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Alpha\Journal\Models\Scopes\JournalScope;
use Corasoft\AlphaScholar\Metadata\MultilingualCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use BelongsToJournal;

    protected $fillable = [
        'journal_id', 'volume', 'number', 'year', 'title', 'description',
        'is_published', 'publish_at', 'doi',
    ];

    protected function casts(): array
    {
        return [
            'title' => MultilingualCast::class,
            'description' => MultilingualCast::class,
            'is_published' => 'boolean',
            'publish_at' => 'datetime',
        ];
    }

    public function galleys(): HasMany
    {
        return $this->hasMany(IssueGalley::class)->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class)->withoutGlobalScope(JournalScope::class);
    }

    public function editorAssignments(): HasMany
    {
        return $this->hasMany(IssueEditorAssignment::class);
    }

    public function isEditedBy(User $user): bool
    {
        return $this->editorAssignments()->where('user_id', $user->id)->exists();
    }

    public function identification(): string
    {
        $parts = array_filter([
            $this->volume ? "Vol. {$this->volume}" : null,
            $this->number ? "No. {$this->number}" : null,
            $this->year,
        ]);

        return implode(', ', $parts);
    }
}
