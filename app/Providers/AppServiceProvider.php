<?php

namespace Alpha\Journal\Providers;

use Alpha\Journal\Ai\AiContext;
use Alpha\Journal\Events\SubmissionStageChanged;
use Alpha\Journal\Listeners\DepositToCrossref;
use Alpha\Journal\Listeners\DepositToDataCite;
use Alpha\Journal\Listeners\DepositToDoaj;
use Alpha\Journal\Listeners\PushWorksToOrcid;
use Alpha\Journal\Listeners\SendDecisionNotification;
use Alpha\Journal\Notifications\Telegram\TelegramChannel;
use Alpha\Journal\Support\CurrentSite;
use Alpha\Journal\Support\JournalContext;
use Alpha\Journal\Support\Secrets\IntegrationCredentials;
use Alpha\Journal\Support\Secrets\StorageCredentials;
use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JournalContext::class);

        // One Site row per installation, read by the Inertia share, the blade root view and
        // Locales — memoised so that is one query per request rather than three.
        $this->app->singleton(CurrentSite::class);

        // Must be a singleton. EditorialDecisionBuilder's AI write-guard resolves this same
        // instance to ask "is an AI call in flight?" — a per-resolution instance would hand the
        // guard a fresh object that always answers no, silently disarming it.
        $this->app->singleton(AiContext::class);

        /*
         * Where a new upload lands: the bucket once Admin → Storage is filled in, the local
         * disk until then.
         *
         * The disk is resolved inside a closure, per call, rather than baked in when the
         * service is built. An admin who saves credentials expects the very next upload to go
         * to the bucket, not the next deploy — and the queue worker running a migration holds
         * this object for a long time.
         */
        $this->app->bind(FileStorage::class, fn () => new FileStorage(
            fn () => $this->cloudStorageReady() ? FileStorage::CLOUD_DISK : FileStorage::LOCAL_DISK,
        ));
    }

    /**
     * Whether the configured bucket is usable, and applies its settings to the disk.
     *
     * Wrapped: this runs before the first migration on a fresh checkout, where the sites table
     * does not exist yet. Storage is not worth taking the application down for — an install
     * with no answer keeps writing locally, which is the correct default anyway.
     */
    private function cloudStorageReady(): bool
    {
        try {
            $credentials = $this->app->make(StorageCredentials::class);

            if (! $credentials->isConfigured()) {
                return false;
            }

            $intended = $credentials->diskConfig();

            // Only when it actually changes. Rebuilding unconditionally would discard a disk
            // instance someone else deliberately put there — Storage::fake() in a test is the
            // obvious case — and would do it on every single resolution.
            if (config('filesystems.disks.'.FileStorage::CLOUD_DISK) !== $intended) {
                config(['filesystems.disks.'.FileStorage::CLOUD_DISK => $intended]);

                // The manager caches a built disk against its old config, so a rotated key
                // would otherwise keep using the previous one for the life of the process.
                Storage::forgetDisk(FileStorage::CLOUD_DISK);
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // The head partial needs the system name for the tab title on first paint, before any
        // JS runs.
        //
        // Bound to the PARTIAL, not to 'app'. It was bound to the root view, and then the
        // article page arrived with its own root view ('article') that includes the same
        // partial — so every public article page died on an undefined $systemName. Composing
        // the partial means any root view that includes it is served, including the next one.
        View::composer('partials.head', fn ($view) => $view->with('systemName', app(CurrentSite::class)->name()));

        Notification::extend(TelegramChannel::class, fn ($app) => $app->make(TelegramChannel::class));

        // Deferred: the MailManager is only resolved when something actually sends. Calling
        // configureMailer() directly here would put a database read on every single web request
        // to configure a mailer most of them never use.
        $this->app->resolving(MailManager::class, fn () => $this->configureMailer());

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

    /**
     * Injects the stored Brevo SMTP credential into the mailer at boot.
     *
     * Config rather than a custom transport: Brevo's SMTP relay is ordinary SMTP, so Laravel
     * needs no new code to speak it. Doing it here rather than in .env is what lets a site admin
     * rotate the key from Admin → Notifications without a deploy.
     *
     * Skipped entirely when nothing is stored, so MAIL_MAILER keeps governing — an install that
     * has always used its own SMTP server is not switched to Brevo behind its back.
     */
    private function configureMailer(): void
    {
        try {
            $credentials = $this->app->make(IntegrationCredentials::class);
            $key = $credentials->for(IntegrationCredentials::BREVO_SMTP_KEY);
        } catch (Throwable) {
            // No sites table yet (first migrate) or no database at all. Mail configuration is
            // not worth taking the application down for — MAIL_MAILER stays in charge.
            return;
        }

        if (blank($key)) {
            return;
        }

        config([
            'mail.mailers.brevo.transport' => 'smtp',
            'mail.mailers.brevo.host' => config('services.integrations.brevo_host', 'smtp-relay.brevo.com'),
            'mail.mailers.brevo.port' => (int) config('services.integrations.brevo_port', 587),
            'mail.mailers.brevo.encryption' => 'tls',
            'mail.mailers.brevo.username' => $credentials->for(IntegrationCredentials::BREVO_SMTP_USER),
            'mail.mailers.brevo.password' => $key,
            'mail.default' => 'brevo',
        ]);
    }
}
