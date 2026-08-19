<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Corasoft\AlphaScholar\Metadata\MultilingualCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use BelongsToJournal;

    protected $fillable = [
        'journal_id', 'title', 'abbreviation', 'policy', 'review_form_id', 'word_limit',
        'review_not_required', 'not_in_toc', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'title' => MultilingualCast::class,
            'abbreviation' => MultilingualCast::class,
            'policy' => MultilingualCast::class,
            'review_not_required' => 'boolean',
            'not_in_toc' => 'boolean',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function reviewForm(): BelongsTo
    {
        return $this->belongsTo(ReviewForm::class);
    }
}
