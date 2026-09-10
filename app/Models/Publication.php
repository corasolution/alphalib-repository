<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Scopes\JournalScope;
use Corasoft\AlphaScholar\Metadata\MultilingualCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Publication extends Model
{
    protected $fillable = ['submission_id', 'version', 'title', 'abstract', 'status', 'published_at', 'doi'];

    protected function casts(): array
    {
        return [
            'title' => MultilingualCast::class,
            'abstract' => MultilingualCast::class,
            'published_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(Contributor::class)->orderBy('sequence');
    }

    /** Downloadable renditions of this version — the PDF or HTML a reader opens (§7). */
    public function galleys(): HasMany
    {
        return $this->hasMany(PublicationGalley::class)->orderBy('sort_order');
    }

    public function preservationDeposit(): HasOne
    {
        return $this->hasOne(PreservationDeposit::class);
    }

    /**
     * The same double gate, across every enabled journal on the site — what reader-facing search
     * runs over.
     *
     * The enabled check matters here in a way it does not on a journal's own page: a disabled
     * journal's URL 404s, so its articles are unreachable, and search must not be the back door
     * that surfaces them anyway.
     */
    public function scopePublishedInAnyJournal(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereHas('submission', fn ($submission) => $submission
                ->withoutGlobalScope(JournalScope::class)
                ->where('stage', SubmissionStage::Published->value)
                ->whereHas('journal', fn ($journal) => $journal
                    ->withoutGlobalScope(JournalScope::class)
                    ->where('is_enabled', true)));
    }

    /**
     * Publications that are genuinely public for this journal (optionally within one issue).
     *
     * The double gate is the point, and it is a security check rather than a tidiness one:
     * Publication.status alone is not proof of publication. A submission that was published and
     * later withdrawn or declined keeps its old `status = 'published'` row, so filtering on status
     * by itself leaks a retracted article back onto a public page. The submission's own stage has
     * to agree.
     *
     * This rule was independently re-implemented in PublicJournalController and SiteController;
     * it lives here so the copies cannot drift apart, because the failure mode of drift is a leak,
     * not a rendering bug. (OaiRepository keeps its own query — it layers date-range harvesting
     * semantics on top and is not the same question.)
     *
     * withoutGlobalScope: the journal is named explicitly here, so the ambient request-derived
     * JournalScope would only fight it — and this scope is also used from unscoped contexts.
     */
    public function scopePublishedIn(Builder $query, Journal $journal, ?Issue $issue = null): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereHas('submission', function ($submission) use ($journal, $issue) {
                $submission->withoutGlobalScope(JournalScope::class)
                    ->where('journal_id', $journal->id)
                    ->where('stage', SubmissionStage::Published->value);

                if ($issue) {
                    $submission->where('issue_id', $issue->id);
                }
            });
    }
}
