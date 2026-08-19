<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Export\ArticleCsvExporter;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ExportController extends Controller
{
    public function articlesCsv(string $journal, ArticleCsvExporter $exporter): Response
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('view', $currentJournal);

        $csv = $exporter->export($currentJournal);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$currentJournal->path}-articles.csv\"",
        ]);
    }
}
