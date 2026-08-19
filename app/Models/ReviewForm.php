<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Corasoft\AlphaScholar\Metadata\MultilingualCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ReviewForm extends Model
{
    use BelongsToJournal;

    protected $fillable = ['journal_id', 'title', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'title' => MultilingualCast::class,
            'description' => MultilingualCast::class,
            'is_active' => 'boolean',
        ];
    }

    public function elements(): HasMany
    {
        return $this->hasMany(ReviewFormElement::class)->orderBy('sort_order');
    }

    /**
     * @param  array<int, mixed>  $answersByElementId
     * @return Collection<int, ReviewFormElement> required elements with no answer supplied
     */
    public function missingRequiredElements(array $answersByElementId): Collection
    {
        return $this->elements()
            ->where('is_required', true)
            ->get()
            ->filter(function (ReviewFormElement $element) use ($answersByElementId) {
                $value = $answersByElementId[$element->id] ?? null;

                return $value === null || $value === '' || $value === [];
            });
    }
}
