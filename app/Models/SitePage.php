<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SitePage extends Model
{
    protected $fillable = ['site_id', 'slug', 'title', 'body', 'layout'];

    /** Renders the editorial board card grid beneath the body. Null means plain prose. */
    public const LAYOUT_EDITORIAL_BOARD = 'editorial_board';

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * A URL slug for a title.
     *
     * Falls back to 'page' when slugification yields nothing. Str::slug strips anything it cannot
     * transliterate, so a title written entirely in Khmer — which this app now fully supports —
     * produced an empty string, and the page was created at /pages/ with a slug that collided
     * with the next one. A generic-but-real slug beats a broken URL.
     */
    public static function slugFor(string $title): string
    {
        $slug = Str::slug($title);

        return $slug !== '' ? $slug : 'page';
    }

    /**
     * The same, made unique within a site by appending -2, -3 and so on.
     *
     * Needed because the fallback above is deliberately generic: two Khmer-titled pages would
     * otherwise both want 'page' and the second would fail validation with a message about a
     * field the author never filled in.
     */
    public static function uniqueSlugFor(Site $site, string $title, ?int $ignoreId = null): string
    {
        $base = self::slugFor($title);
        $slug = $base;
        $suffix = 1;

        while (
            static::where('site_id', $site->id)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
