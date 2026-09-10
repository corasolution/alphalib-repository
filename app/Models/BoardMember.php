<?php

namespace Alpha\Journal\Models;

use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

/**
 * One person on the public editorial board. Follows HeroSlide's shape for the photo columns —
 * same FileStorage service, same disk/path/original_name/mime_type/size quintet, same
 * temporary-URL convention as Site::logoUrl().
 */
class BoardMember extends Model
{
    protected $fillable = [
        'board_group_id', 'name', 'role', 'affiliation',
        'disk', 'path', 'original_name', 'mime_type', 'size', 'sort_order',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(BoardGroup::class, 'board_group_id');
    }

    /** @param array<string, mixed> $attributes */
    public static function createFor(BoardGroup $group, array $attributes): self
    {
        return static::create($attributes + [
            'board_group_id' => $group->id,
            'sort_order' => 1 + (int) static::where('board_group_id', $group->id)->max('sort_order'),
        ]);
    }

    public function storePhoto(UploadedFile $file): void
    {
        $stored = app(FileStorage::class)->store($file, 'site/board-members');

        $this->update([
            'disk' => $stored->disk,
            'path' => $stored->path,
            'original_name' => $stored->originalName,
            'mime_type' => $stored->mimeType,
            'size' => $stored->size,
        ]);
    }

    /**
     * Clears the photo columns so the card falls back to its initials avatar.
     *
     * The stored file itself is left in place on purpose: FileStorage is content-addressed, so
     * deleting the object could pull the rug from under any other row that happens to reference
     * the same bytes. An orphaned image costs a few KB; a broken one is visible on a public page.
     */
    public function removePhoto(): void
    {
        $this->update([
            'disk' => null, 'path' => null, 'original_name' => null,
            'mime_type' => null, 'size' => null,
        ]);
    }

    public function photoUrl(): ?string
    {
        if ($this->path === null) {
            return null;
        }

        return app(FileStorage::class)->temporaryUrl($this->path, now()->addDay(), $this->disk);
    }

    /**
     * Up to two letters for the fallback avatar. Titles are stripped first, otherwise every
     * doctor on the board gets an identical "DR" circle.
     */
    public function initials(): string
    {
        $words = collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->reject(fn (string $word) => in_array(rtrim(mb_strtolower($word), '.'), ['dr', 'prof', 'mr', 'ms', 'mrs'], true))
            ->filter()
            ->values();

        if ($words->isEmpty()) {
            return mb_strtoupper(mb_substr(trim($this->name), 0, 1)) ?: '?';
        }

        return $words->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }

    /** @return array<string, mixed> The public card payload. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'affiliation' => $this->affiliation,
            'photoUrl' => $this->photoUrl(),
            'initials' => $this->initials(),
        ];
    }

    /** The CMS list row. Same initials as the public card, so a screen of doctors is not all "DR". */
    public function toEditorRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role ?? '',
            'affiliation' => $this->affiliation ?? '',
            'photoUrl' => $this->photoUrl(),
            'initials' => $this->initials(),
            'sortOrder' => $this->sort_order,
        ];
    }
}
