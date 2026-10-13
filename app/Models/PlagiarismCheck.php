<?php

namespace Alpha\Journal\Models;

use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

/**
 * One similarity screening of a manuscript.
 *
 * The result is entered by an editor from whatever tool the institution licences, so this model
 * holds no vendor assumptions beyond a free-text tool name. What it does enforce is that a
 * recorded check always says who ran it and when — an unattributed "it was checked" is worth
 * nothing when a paper is later challenged.
 */
class PlagiarismCheck extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FLAGGED = 'flagged';

    public const STATUSES = [
        self::STATUS_PENDING => 'Awaiting report',
        self::STATUS_PASSED => 'Passed',
        self::STATUS_FLAGGED => 'Flagged for review',
    ];

    protected $fillable = [
        'submission_id', 'status', 'similarity_percent', 'tool', 'notes',
        'disk', 'path', 'original_name', 'mime_type', 'size',
        'checked_by', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'similarity_percent' => 'integer',
            'size' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function hasReport(): bool
    {
        return filled($this->path);
    }

    /** Signed, short-lived link to the report, the same way a galley is served. */
    public function url(): string
    {
        return app(FileStorage::class)->temporaryUrl($this->path, now()->addHour(), $this->disk);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Records a check, optionally with the tool's report attached.
     *
     * checked_at is explicit rather than leaning on created_at: a report often arrives days after
     * it was run, and the date that matters editorially is when the manuscript was screened.
     */
    public static function record(
        Submission $submission,
        User $editor,
        string $status,
        ?int $similarity = null,
        ?string $tool = null,
        ?string $notes = null,
        ?UploadedFile $report = null,
    ): self {
        $stored = $report
            ? app(FileStorage::class)->store($report, "submissions/{$submission->id}/plagiarism")
            : null;

        return static::create([
            'submission_id' => $submission->id,
            'status' => $status,
            'similarity_percent' => $similarity,
            'tool' => $tool,
            'notes' => $notes,
            'disk' => $stored?->disk,
            'path' => $stored?->path,
            'original_name' => $stored?->originalName,
            'mime_type' => $stored?->mimeType,
            'size' => $stored?->size,
            'checked_by' => $editor->id,
            'checked_at' => now(),
        ]);
    }
}
