<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contributor extends Model
{
    protected $fillable = [
        'publication_id', 'given_name', 'family_name', 'email', 'orcid',
        'affiliation', 'ror_id', 'role', 'sequence',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function fullName(): string
    {
        return trim("{$this->given_name} {$this->family_name}");
    }
}
