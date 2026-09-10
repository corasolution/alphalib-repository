<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\SubmissionStage;
use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

/**
 * One current manuscript at all times, plus a version history strip (CLAUDE.md §13.3) — stage is
 * metadata on the version, not a separate bucket ("Review Files" / "Copyedited" / etc).
 */
class SubmissionFile extends Model
{
    protected $fillable = [
        'submission_id', 'uploaded_by', 'stage', 'version', 'is_current_manuscript',
        'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256',
    ];

    protected function casts(): array
    {
        return [
            'stage' => SubmissionStage::class,
            'is_current_manuscript' => 'boolean',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Stores the upload on R2 via the shared FileStorage service, versions it against any prior
     * manuscript for this submission, and promotes it to the current manuscript.
     */
    public static function storeManuscript(
        Submission $submission,
        UploadedFile $file,
        User $uploader,
        ?SubmissionStage $stage = null,
    ): self {
        $stored = app(FileStorage::class)->store($file, "submissions/{$submission->id}");

        $nextVersion = 1 + (int) static::where('submission_id', $submission->id)
            ->where('is_current_manuscript', true)
            ->max('version');

        static::where('submission_id', $submission->id)
            ->where('is_current_manuscript', true)
            ->update(['is_current_manuscript' => false]);

        return static::create([
            'submission_id' => $submission->id,
            'uploaded_by' => $uploader->id,
            'stage' => ($stage ?? $submission->stage)->value,
            'version' => $nextVersion,
            'is_current_manuscript' => true,
            'disk' => $stored->disk,
            'path' => $stored->path,
            'original_name' => $stored->originalName,
            'mime_type' => $stored->mimeType,
            'size' => $stored->size,
            'sha256' => $stored->sha256,
        ]);
    }

    /**
     * Non-manuscript uploads — "Figures and tables", "Supporting data", "Cover letter" in the
     * author-facing vocabulary (CLAUDE.md §13.1). Never touches is_current_manuscript, so it
     * cannot demote the actual manuscript the way storeManuscript() intentionally does.
     */
    public static function storeSupporting(
        Submission $submission,
        UploadedFile $file,
        User $uploader,
        ?SubmissionStage $stage = null,
    ): self {
        $stored = app(FileStorage::class)->store($file, "submissions/{$submission->id}");

        return static::create([
            'submission_id' => $submission->id,
            'uploaded_by' => $uploader->id,
            'stage' => ($stage ?? $submission->stage)->value,
            'version' => 1,
            'is_current_manuscript' => false,
            'disk' => $stored->disk,
            'path' => $stored->path,
            'original_name' => $stored->originalName,
            'mime_type' => $stored->mimeType,
            'size' => $stored->size,
            'sha256' => $stored->sha256,
        ]);
    }
}
