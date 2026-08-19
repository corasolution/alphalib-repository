<?php

namespace Alpha\Journal\Models;

use Corasoft\AlphaScholar\Metadata\MultilingualCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Publication extends Model
{
    protected $fillable = ['submission_id', 'version', 'title', 'abstract', 'status', 'published_at', 'doi'];

    protected function casts(): array
    {
        return [
            'title' => MultilingualCast::class,
            'abstract' => MultilingualCast::class,
            'published_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(Contributor::class)->orderBy('sequence');
    }

    public function preservationDeposit(): HasOne
    {
        return $this->hasOne(PreservationDeposit::class);
    }
}
