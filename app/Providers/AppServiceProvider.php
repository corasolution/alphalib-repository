<?php

namespace Alpha\Journal\Providers;

use Alpha\Journal\Events\SubmissionStageChanged;
use Alpha\Journal\Listeners\DepositToCrossref;
use Alpha\Journal\Listeners\DepositToDataCite;
use Alpha\Journal\Listeners\DepositToDoaj;
use Alpha\Journal\Listeners\PushWorksToOrcid;
use Alpha\Journal\Listeners\SendDecisionNotification;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JournalContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // Catches silently-dropped mass-assignment (missing $fillable entries) with a loud
        // exception in dev/test instead of a quiet no-op — this exact bug shipped once already
        // (ReviewAssignment::$fillable missing new columns). Not enabling preventLazyLoading()
        // or preventAccessingMissingAttributes(): the former needs an eager-loading audit we
        // haven't done, and the latter throws on Auth::user() inside policy before() hooks in a
        // way that needs its own investigation — not worth blocking this guard on either.
        if (! $this->app->isProduction()) {
            Model::preventSilentlyDiscardingAttributes();
        }

        // Notifications subscribe to workflow events; they never call SubmissionWorkflow
        // directly (CLAUDE.md §3).
        Event::listen(SubmissionStageChanged::class, SendDecisionNotification::class);
        Event::listen(SubmissionStageChanged::class, PushWorksToOrcid::class);
        Event::listen(SubmissionStageChanged::class, DepositToDoaj::class);
        Event::listen(SubmissionStageChanged::class, DepositToCrossref::class);
        Event::listen(SubmissionStageChanged::class, DepositToDataCite::class);
    }
}
