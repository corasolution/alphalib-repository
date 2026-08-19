<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageAssignment extends Model
{
    protected $fillable = ['submission_id', 'user_id', 'stage', 'role'];

    protected function casts(): array
    {
        return [
            'stage' => SubmissionStage::class,
            'role' => JournalRole::class,
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
