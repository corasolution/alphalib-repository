<?php

namespace Alpha\Journal\Models;

use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

/**
 * One entry in the "Indexed by" or "Tools" strip on the public site.
 *
 * Deliberately admin-entered and empty by default. "Indexed by X" is a factual claim to authors
 * and readers about where this journal's articles can be found — the sort of claim predatory
 * journals make falsely — so nothing seeds itself here and nothing appears until somebody with
 * authority asserts it.
 */
class SiteBadge extends Model
{
    public const GROUP_INDEXED_BY = 'indexed_by';

    public const GROUP_TOOLS = 'tools';

    public const GROUPS = [
        self::GROUP_INDEXED_BY => 'Indexed by',
        self::GROUP_TOOLS => 'Tools',
    ];

    protected $fillable = [
        'site_id', 'group', 'label', 'url',
        'disk', 'path', 'original_name', 'mime_type', 'size', 'sort_order',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public static function storeFor(Site $site, string $group, string $label, ?string $url, ?UploadedFile $logo): self
    {
        $stored = $logo ? app(FileStorage::class)->store($logo, 'site/badges') : null;

        return static::create([
            'site_id' => $site->id,
            'group' => $group,
            'label' => $label,
            'url' => $url,
            'disk' => $stored?->disk,
            'path' => $stored?->path,
            'original_name' => $stored?->originalName,
            'mime_type' => $stored?->mimeType,
            'size' => $stored?->size,
            'sort_order' => 1 + (int) static::where('site_id', $site->id)->where('group', $group)->max('sort_order'),
        ]);
    }

    /** Replaces the image on an existing badge, keeping its label, link and position. */
    public function replaceLogo(UploadedFile $logo): void
    {
        $stored = app(FileStorage::class)->store($logo, 'site/badges');

        $this->update([
            'disk' => $stored->disk,
            'path' => $stored->path,
            'original_name' => $stored->originalName,
            'mime_type' => $stored->mimeType,
            'size' => $stored->size,
        ]);
    }

    public function logoUrl(): ?string
    {
        if ($this->path === null) {
            return null;
        }

        return app(FileStorage::class)->temporaryUrl($this->path, now()->addDay(), $this->disk);
    }
}
