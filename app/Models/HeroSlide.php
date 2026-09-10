<?php

namespace Alpha\Journal\Models;

use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

class HeroSlide extends Model
{
    protected $fillable = [
        'site_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'caption', 'sort_order',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public static function storeFor(Site $site, UploadedFile $file, ?string $caption = null): self
    {
        $stored = app(FileStorage::class)->store($file, 'site/hero-slides');

        $nextSortOrder = 1 + (int) static::where('site_id', $site->id)->max('sort_order');

        return static::create([
            'site_id' => $site->id,
            'disk' => $stored->disk,
            'path' => $stored->path,
            'original_name' => $stored->originalName,
            'mime_type' => $stored->mimeType,
            'size' => $stored->size,
            'caption' => $caption,
            'sort_order' => $nextSortOrder,
        ]);
    }

    public function url(): string
    {
        return app(FileStorage::class)->temporaryUrl($this->path, now()->addDay(), $this->disk);
    }
}
