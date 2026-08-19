<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable compliance record (CLAUDE.md §2, §4.2). Rows are written once and never changed.
 * newEloquentBuilder() blocks both $model->update()/delete() *and* the bulk
 * ActivityLog::where(...)->update()/delete() paths, which bypass any instance-level override —
 * see ImmutableBuilder for why that distinction matters.
 */
class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $fillable = ['journal_id', 'user_id', 'action', 'subject_type', 'subject_id', 'description', 'properties'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function newEloquentBuilder($query): Builder
    {
        return new ImmutableBuilder($query);
    }
}
