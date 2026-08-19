<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewerProfile extends Model
{
    use BelongsToJournal;

    protected $fillable = ['journal_id', 'user_id', 'interests', 'is_available', 'unavailable_until'];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'unavailable_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Available means both the manual toggle is on and any declared "unavailable until" date
     * has passed.
     */
    public function isCurrentlyAvailable(): bool
    {
        if (! $this->is_available) {
            return false;
        }

        return $this->unavailable_until === null || $this->unavailable_until->isPast();
    }
}
