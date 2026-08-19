<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Without automated reminders, review turnaround collapses (CLAUDE.md §11.2).
Schedule::command('journal:send-reviewer-reminders')->daily();
Schedule::command('journal:escalate-overdue-reviews')->daily();
Schedule::command('journal:alert-unclaimed-submissions')->daily();

// Scheduled BagIt/OAIS export to R2 as an institutional dark archive (CLAUDE.md §5.5).
Schedule::command('journal:export-preservation-bags')->daily();
