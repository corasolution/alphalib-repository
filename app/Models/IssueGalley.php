<?php

namespace Alpha\Journal\Models;

use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

class IssueGalley extends Model
{
    protected $fillable = [
        'issue_id', 'label', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'sort_order',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * The full-issue file (PDF/HTML/etc, labeled by format) — OJS Production stage's "create a
     * galley" step, at the issue level (this schema doesn't model per-article galleys).
     */
    public static function storeFor(Issue $issue, UploadedFile $file, string $label): self
    {
        $stored = app(FileStorage::class)->store($file, "issues/{$issue->id}");

        $nextSortOrder = 1 + (int) static::where('issue_id', $issue->id)->max('sort_order');

        return static::create([
            'issue_id' => $issue->id,
            'label' => $label,
            'disk' => $stored->disk,
            'path' => $stored->path,
            'original_name' => $stored->originalName,
            'mime_type' => $stored->mimeType,
            'size' => $stored->size,
            'sha256' => $stored->sha256,
            'sort_order' => $nextSortOrder,
        ]);
    }
}
