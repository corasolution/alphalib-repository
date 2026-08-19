<?php

namespace Alpha\Journal\Models\Scopes;

use Alpha\Journal\Support\JournalContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query to the journal resolved by JournalContext (CLAUDE.md §2 hard rule).
 * Deny-by-default when no journal is resolved (e.g. a site-admin/cross-journal query) — callers
 * that legitimately need cross-journal access must opt out explicitly with
 * Model::withoutGlobalScope(JournalScope::class), never rely on an implicit fallback.
 *
 * Console (artisan/tinker/queue workers) bypasses the deny-by-default so seeders and jobs that
 * already pass a trusted journal_id aren't blocked; set config('journal.scope_console_bypass')
 * to false to exercise the real deny path in a test.
 */
class JournalScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(JournalContext::class);

        if ($context->has()) {
            $builder->where($model->qualifyColumn('journal_id'), $context->get()->id);

            return;
        }

        if (app()->runningInConsole() && config('journal.scope_console_bypass', true)) {
            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
