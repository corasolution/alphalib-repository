<?php

use Alpha\Journal\Http\Middleware\HandleInertiaRequests;
use Alpha\Journal\Http\Middleware\ResolveJournalContext;
use Alpha\Journal\Http\Middleware\SetLocale;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // Before Inertia: HandleInertiaRequests shares the active locale and its translations,
            // so the locale has to be applied to the app before that share() runs.
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'journal' => ResolveJournalContext::class,
        ]);

        // ResolveJournalContext must run before SubstituteBindings, or any journal-scoped model
        // (Submission, Section, ...) used as an implicit route-model-binding parameter 404s: the
        // binding query runs under JournalScope's deny-by-default before JournalContext is set.
        // Console/test runs never hit this — JournalScope bypasses entirely when
        // app()->runningInConsole() is true, which is always true under `php artisan test` even
        // for HTTP feature tests — so this class of bug is invisible to the test suite and only
        // shows up against a real request pipeline.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveJournalContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
         * An upload larger than PHP's post_max_size is rejected by ValidatePostSize before any
         * route, validation rule or form request runs, so nothing downstream can turn it into a
         * message. Left alone it renders the raw exception page and the author loses everything
         * they typed.
         *
         * UploadLimits keeps the stated limit honest, and the browser-side check in FileDrop
         * stops the common case before a byte is sent — but several files can still add up past
         * the request limit, so this stays as the backstop.
         */
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = 'That upload is too large. Everything you send at once must add up to less than '
                .UploadLimits::postMaxLabel().'. Try a smaller file, or add supporting files after submitting.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->withInput($request->except(['manuscript', 'supporting_files']))
                ->withErrors(['manuscript' => $message]);
        });
    })->create();
