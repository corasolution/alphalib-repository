<?php

namespace Alpha\Journal\Models;

use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

/**
 * One downloadable rendition of an article — the PDF or HTML a reader opens.
 *
 * Mirrors IssueGalley's shape deliberately (same FileStorage service, same column quintet), but
 * hangs off the publication: galleys belong to a version, so a corrected version starts without
 * its predecessor's files rather than silently inheriting a PDF that no longer matches the text.
 */
class PublicationGalley extends Model
{
    protected $fillable = [
        'publication_id', 'label', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'sort_order',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public static function storeFor(Publication $publication, UploadedFile $file, string $label): self
    {
        $stored = app(FileStorage::class)->store($file, "articles/{$publication->id}");

        return static::create([
            'publication_id' => $publication->id,
            'label' => $label,
            'disk' => $stored->disk,
            'path' => $stored->path,
            'original_name' => $stored->originalName,
            'mime_type' => $stored->mimeType,
            'size' => $stored->size,
            'sha256' => $stored->sha256,
            'sort_order' => 1 + (int) static::where('publication_id', $publication->id)->max('sort_order'),
        ]);
    }

    /** Short-lived signed URL, same convention as every other stored file in the app. */
    public function url(): string
    {
        return app(FileStorage::class)->temporaryUrl($this->path, now()->addHour(), $this->disk);
    }

    public function humanSize(): ?string
    {
        if ($this->size === null) {
            return null;
        }

        return $this->size < 1048576
            ? round($this->size / 1024).' KB'
            : round($this->size / 1048576, 1).' MB';
    }
}
