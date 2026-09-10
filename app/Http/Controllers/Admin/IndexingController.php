<?php

namespace Alpha\Journal\Http\Controllers\Admin;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Support\DepositSettings;
use Alpha\Journal\Support\Secrets\IntegrationCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where a site admin switches on automatic deposit to Crossref and DataCite.
 *
 * These credentials used to be reachable only by editing .env on the server, which meant the
 * difference between "indexed" and "not indexed" was a deploy, and the live/test endpoint — the
 * setting that silently sends every DOI to a sandbox — was a URL nobody looked at twice.
 *
 * The screen therefore reports readiness rather than just holding fields: deposit only fires for
 * a journal that has a DOI prefix, so a perfectly configured Crossref account still deposits
 * nothing until that is set, and the admin should be able to see why.
 */
class IndexingController extends Controller
{
    /** What the admin can save, mapped to where it is stored. */
    private const FIELDS = [
        'crossref_username' => IntegrationCredentials::CROSSREF_USERNAME,
        'crossref_password' => IntegrationCredentials::CROSSREF_PASSWORD,
        'crossref_depositor_name' => IntegrationCredentials::CROSSREF_DEPOSITOR_NAME,
        'crossref_depositor_email' => IntegrationCredentials::CROSSREF_DEPOSITOR_EMAIL,
        'datacite_username' => IntegrationCredentials::DATACITE_USERNAME,
        'datacite_password' => IntegrationCredentials::DATACITE_PASSWORD,
    ];

    public function index(IntegrationCredentials $credentials, DepositSettings $settings): Response
    {
        Gate::authorize('manage', \Alpha\Journal\Models\Site::firstOrFail());

        return Inertia::render('admin/indexing', [
            'crossref' => [
                'configured' => $settings->crossrefIsConfigured(),
                'live' => $settings->crossrefIsLive(),
                'endpoint' => $settings->crossrefDepositUrl(),
                'depositorName' => $settings->crossrefDepositorName(),
                'depositorEmail' => $settings->crossrefDepositorEmail(),
                'username' => $settings->crossrefUsername(),
                'source' => $credentials->source(IntegrationCredentials::CROSSREF_PASSWORD),
                'hint' => $credentials->hint(IntegrationCredentials::CROSSREF_PASSWORD),
            ],
            'datacite' => [
                'configured' => $settings->dataciteIsConfigured(),
                'live' => $settings->dataciteIsLive(),
                'endpoint' => $settings->dataciteBaseUrl(),
                'username' => $settings->dataciteUsername(),
                'source' => $credentials->source(IntegrationCredentials::DATACITE_PASSWORD),
                'hint' => $credentials->hint(IntegrationCredentials::DATACITE_PASSWORD),
            ],
            // Readiness per journal. A credential without a prefix deposits nothing, and that is
            // the failure people actually hit, so it is shown next to the credentials.
            'journals' => Journal::withoutGlobalScopes()->orderBy('path')->get()->map(fn (Journal $journal) => [
                'path' => $journal->path,
                'title' => $journal->title->get($journal->primary_locale, 'en') ?? $journal->path,
                'enabled' => (bool) $journal->is_enabled,
                'doiPrefix' => $journal->setting('doi_prefix'),
                'issnOnline' => $journal->setting('issn_online'),
                'doajKey' => filled($journal->setting('doaj_api_key')),
            ]),
            'oaiUrl' => url('/oai'),
        ]);
    }

    public function update(Request $request, IntegrationCredentials $credentials, DepositSettings $settings): RedirectResponse
    {
        Gate::authorize('manage', \Alpha\Journal\Models\Site::firstOrFail());

        $data = $request->validate([
            'crossref_username' => ['nullable', 'string', 'max:255'],
            'crossref_password' => ['nullable', 'string', 'max:255'],
            'crossref_depositor_name' => ['nullable', 'string', 'max:255'],
            'crossref_depositor_email' => ['nullable', 'email', 'max:255'],
            'datacite_username' => ['nullable', 'string', 'max:255'],
            'datacite_password' => ['nullable', 'string', 'max:255'],
            'crossref_live' => ['required', 'boolean'],
            'datacite_live' => ['required', 'boolean'],
        ]);

        // A blank field leaves the stored secret alone. The screen never sends a secret back to
        // the browser, so an admin editing the depositor email must not wipe the password by
        // submitting a form whose password box is necessarily empty. Clearing is a separate
        // action (forget), for the same reason it is on the storage screen.
        foreach (self::FIELDS as $field => $key) {
            if (filled($data[$field] ?? null)) {
                $credentials->put($key, $data[$field]);
            }
        }

        $settings->setLive('crossref', $data['crossref_live']);
        $settings->setLive('datacite', $data['datacite_live']);

        return back()->with('success', 'Indexing settings saved.');
    }

    public function forget(Request $request, IntegrationCredentials $credentials): RedirectResponse
    {
        Gate::authorize('manage', \Alpha\Journal\Models\Site::firstOrFail());

        $service = $request->validate([
            'service' => ['required', Rule::in(['crossref', 'datacite'])],
        ])['service'];

        foreach (self::FIELDS as $field => $key) {
            if (str_starts_with($field, $service)) {
                $credentials->put($key, null);
            }
        }

        return back()->with('success', ucfirst($service).' credentials removed.');
    }

    /**
     * Proves the credentials are accepted before an article's publication depends on them.
     *
     * Crossref has no "verify login" call, so this posts an empty submission and reads the
     * rejection: a credential problem answers differently from a malformed-batch problem, and
     * telling those apart is the whole value of the button.
     */
    public function test(Request $request, DepositSettings $settings): JsonResponse
    {
        Gate::authorize('manage', \Alpha\Journal\Models\Site::firstOrFail());

        $service = $request->validate([
            'service' => ['required', Rule::in(['crossref', 'datacite'])],
        ])['service'];

        return $service === 'crossref'
            ? $this->testCrossref($settings)
            : $this->testDataCite($settings);
    }

    private function testCrossref(DepositSettings $settings): JsonResponse
    {
        if (! $settings->crossrefIsConfigured()) {
            return response()->json(['ok' => false, 'message' => 'Enter a Crossref username and password first.']);
        }

        try {
            $response = Http::timeout(20)
                ->asForm()
                ->post($settings->crossrefDepositUrl(), [
                    'operation' => 'doQueryUpload',
                    'login_id' => $settings->crossrefUsername(),
                    'login_passwd' => $settings->crossrefPassword(),
                ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not reach Crossref: '.$e->getMessage()]);
        }

        $body = $response->body();

        if (str_contains(strtolower($body), 'login') && str_contains(strtolower($body), 'fail')) {
            return response()->json(['ok' => false, 'message' => 'Crossref rejected the username or password.']);
        }

        return response()->json([
            'ok' => $response->successful(),
            'message' => $response->successful()
                ? 'Crossref accepted the credentials on the '.($settings->crossrefIsLive() ? 'live' : 'test').' endpoint.'
                : "Crossref answered HTTP {$response->status()}.",
        ]);
    }

    private function testDataCite(DepositSettings $settings): JsonResponse
    {
        if (! $settings->dataciteIsConfigured()) {
            return response()->json(['ok' => false, 'message' => 'Enter a DataCite username and password first.']);
        }

        try {
            $response = Http::timeout(20)
                ->withBasicAuth($settings->dataciteUsername(), $settings->datacitePassword())
                ->get(rtrim($settings->dataciteBaseUrl(), '/').'/dois', ['page[size]' => 1]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not reach DataCite: '.$e->getMessage()]);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return response()->json(['ok' => false, 'message' => 'DataCite rejected the username or password.']);
        }

        return response()->json([
            'ok' => $response->successful(),
            'message' => $response->successful()
                ? 'DataCite accepted the credentials on the '.($settings->dataciteIsLive() ? 'live' : 'test').' API.'
                : "DataCite answered HTTP {$response->status()}.",
        ]);
    }
}
