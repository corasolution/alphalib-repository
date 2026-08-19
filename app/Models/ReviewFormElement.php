<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\ReviewFormElementType;
use Corasoft\AlphaScholar\Metadata\MultilingualCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewFormElement extends Model
{
    protected $fillable = [
        'review_form_id', 'type', 'label', 'help_text', 'is_required', 'options', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReviewFormElementType::class,
            'label' => MultilingualCast::class,
            'help_text' => MultilingualCast::class,
            'is_required' => 'boolean',
            'options' => 'array',
        ];
    }

    public function reviewForm(): BelongsTo
    {
        return $this->belongsTo(ReviewForm::class);
    }
}
