<?php

namespace Alpha\Journal\Http\Controllers\Admin;

use Alpha\Journal\Ai\AiRunner;
use Alpha\Journal\Ai\Providers\AiCredentials;
use Alpha\Journal\Ai\Providers\ProviderRegistry;
use Alpha\Journal\Enums\AiRunStatus;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\AiRun;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Provider credentials, the installation-wide switch, and the ai_runs audit trail
 * (CLAUDE.md §6 guardrail 3 — the log is "the product's improvement loop and the customer's
 * audit trail", and §4 gives the Auditor role read access to it).
 *
 * A stored key is never sent back to the browser. The screen shows whether one is set, where it
 * came from, and its last four characters — enough for an admin to tell which key is loaded, and
 * useless to anyone who obtains the response.
 */
class AiController extends Controller
{
    /** How many runs the log shows before paging would be needed. */
    private const RECENT_RUNS = 50;

    public function index(ProviderRegistry $registry, AiCredentials $credentials, AiRunner $runner): Response
    {
        Gate::authorize('manage', Site::class);

        return Inertia::render('admin/ai', [
            'providers' => collect($registry->all())->map(fn ($provider, $name) => [
                'name' => $name,
                'label' => $name === 'anthropic' ? 'Anthropic (Claude)' : 'Google (Gemini)',
                'configured' => $provider->isConfigured(),
                'source' => $credentials->source($name),
                'hint' => $credentials->hint($name),
            ])->values(),

            'enabled' => $runner->installationEnabled(),
            'configuredByEnvOnly' => Site::first()?->ai_credentials === null && $registry->anyConfigured(),

            'runs' => AiRun::withoutGlobalScopes()
                ->with('journal:id,path,title')
                ->latest('id')
                ->limit(self::RECENT_RUNS)
                ->get()
                ->map(fn (AiRun $run) => [
                    'id' => $run->id,
                    'feature' => $run->feature->label(),
                    'journal' => $run->journal?->path,
                    'model' => $run->model,
                    'promptVersion' => $run->prompt_version,
                    'status' => $run->status->value,
                    'statusLabel' => $run->status->label(),
                    'inputTokens' => $run->input_tokens,
                    'outputTokens' => $run->output_tokens,
                    'cents' => $run->costInCents(),
                    'latencyMs' => $run->latency_ms,
                    'accepted' => $run->accepted_at !== null ? true : ($run->rejected_at !== null ? false : null),
                    'error' => $run->error,
                    'createdAt' => $run->created_at?->toIso8601String(),
                ]),

            'spend' => $this->spend(),
        ]);
    }

    public function updateKey(Request $request, ProviderRegistry $registry, AiCredentials $credentials): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys($registry->all()))],
            // Nullable so submitting an empty field is how an admin removes a key, falling the
            // installation back to its .env value (or to no AI at all).
            'key' => ['nullable', 'string', 'max:500'],
        ]);

        $credentials->put($data['provider'], $data['key'] ?? null);

        return back()->with('success', filled($data['key'] ?? null) ? 'API key saved.' : 'API key removed.');
    }

    /**
     * One minimal round trip against the provider, so an admin can tell a good key from a typo
     * without waiting for a real submission to fail.
     */
    public function test(Request $request, ProviderRegistry $registry): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate(['provider' => ['required', Rule::in(array_keys($registry->all()))]]);

        try {
            $registry->get($data['provider'])->ping();
        } catch (Throwable $e) {
            // Returned as a normal validation error rather than a 500: a bad key is an expected
            // outcome of this button, not a server fault.
            return back()->withErrors(['key' => 'Connection failed — '.mb_substr($e->getMessage(), 0, 300)]);
        }

        return back()->with('success', 'Connection succeeded.');
    }

    public function updateSwitch(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        Site::firstOrFail()->update(['ai_enabled' => $data['enabled']]);

        return back()->with('success', $data['enabled'] ? 'AI enabled for this installation.' : 'AI disabled for this installation.');
    }

    /**
     * Spend per journal, plus the installation total.
     *
     * Blocked runs are counted separately and cost nothing — they never reached a provider. Their
     * count is worth surfacing because a journal generating many blocked runs usually means
     * someone expects AI to be on and it is not.
     *
     * @return array<string, mixed>
     */
    private function spend(): array
    {
        $rows = AiRun::withoutGlobalScopes()
            ->selectRaw('journal_id, status, count(*) as runs, sum(cost_microcents) as microcents, sum(input_tokens + output_tokens) as tokens')
            ->groupBy('journal_id', 'status')
            ->get();

        $journals = Journal::withoutGlobalScopes()->pluck('path', 'id');

        $byJournal = $rows->groupBy('journal_id')->map(function ($group, $journalId) use ($journals) {
            return [
                'journal' => $journals[$journalId] ?? "#{$journalId}",
                'runs' => (int) $group->sum('runs'),
                'blocked' => (int) $group->where('status', AiRunStatus::Blocked->value)->sum('runs'),
                'failed' => (int) $group->where('status', AiRunStatus::Failed->value)->sum('runs'),
                'tokens' => (int) $group->sum('tokens'),
                'cents' => round($group->sum('microcents') / 10000, 4),
            ];
        })->sortByDesc('cents')->values();

        return [
            'byJournal' => $byJournal,
            'totalRuns' => (int) $rows->sum('runs'),
            'totalCents' => round($rows->sum('microcents') / 10000, 4),
            'totalTokens' => (int) $rows->sum('tokens'),
        ];
    }
}
