<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\SubmissionStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Threaded discussion, per stage (§3). Labeled "Discussion" in the UI (§13.8).
 */
class Query extends Model
{
    protected $table = 'queries';

    protected $fillable = ['submission_id', 'stage', 'title', 'created_by', 'is_closed'];

    protected function casts(): array
    {
        return [
            'stage' => SubmissionStage::class,
            'is_closed' => 'boolean',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(QueryMessage::class)->oldest();
    }
}
