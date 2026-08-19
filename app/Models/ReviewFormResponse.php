<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewFormResponse extends Model
{
    protected $fillable = ['review_assignment_id', 'review_form_element_id', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function reviewAssignment(): BelongsTo
    {
        return $this->belongsTo(ReviewAssignment::class);
    }

    public function reviewFormElement(): BelongsTo
    {
        return $this->belongsTo(ReviewFormElement::class);
    }
}
