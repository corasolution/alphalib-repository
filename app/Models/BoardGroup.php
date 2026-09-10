<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One heading on the public editorial board — "Advisory Council", "Reviewers", and so on.
 *
 * Site-level rather than per-journal: this installation's board serves the site as a whole, and
 * JournalScope deliberately doesn't apply here for the same reason it doesn't to Site.
 */
class BoardGroup extends Model
{
    protected $fillable = ['site_id', 'name', 'sort_order'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(BoardMember::class)->orderBy('sort_order');
    }

    /** Appends to the end of the site's list rather than colliding on sort_order 0. */
    public static function createFor(Site $site, string $name): self
    {
        return static::create([
            'site_id' => $site->id,
            'name' => $name,
            'sort_order' => 1 + (int) static::where('site_id', $site->id)->max('sort_order'),
        ]);
    }
}
