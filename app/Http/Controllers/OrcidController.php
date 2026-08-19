<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Orcid\OrcidClient;
use Alpha\Journal\Orcid\OrcidException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Connect your ORCID account" self-service flow — the user must already be logged in
 * (CLAUDE.md §5.5). This authenticates their existing account against ORCID, it does not log
 * them in via ORCID.
 */
class OrcidController extends Controller
{
    public function redirect(Request $request, OrcidClient $client): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('orcid_oauth_state', $state);

        return redirect($client->authorizeUrl(route('orcid.callback'), $state));
    }

    public function callback(Request $request, OrcidClient $client): RedirectResponse
    {
        $expectedState = $request->session()->pull('orcid_oauth_state');

        if ($expectedState === null || ! $request->filled('state') || ! hash_equals($expectedState, (string) $request->query('state'))) {
            throw new HttpException(403, 'Invalid ORCID OAuth state.');
        }

        if (! $request->filled('code')) {
            throw new HttpException(422, 'ORCID authorization was not completed.');
        }

        try {
            $result = $client->exchangeCode($request->query('code'), route('orcid.callback'));
        } catch (OrcidException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        $request->user()->update([
            'orcid' => $result->orcid,
            'orcid_access_token' => $result->accessToken,
            'orcid_token_expires_at' => $result->expiresIn > 0 ? now()->addSeconds($result->expiresIn) : null,
            'orcid_authenticated_at' => now(),
        ]);

        return redirect()->route('dashboard');
    }
}
