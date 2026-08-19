<?php

namespace Alpha\Journal\Orcid;

use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * ORCID OAuth authenticate flow — not just a typed-in ID — plus pushing published works to the
 * user's ORCID record (CLAUDE.md §5.5).
 */
class OrcidClient
{
    public function authorizeUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => config('services.orcid.client_id'),
            'response_type' => 'code',
            'scope' => config('services.orcid.scope'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return config('services.orcid.authorize_url')."?{$query}";
    }

    public function exchangeCode(string $code, string $redirectUri): OrcidTokenResult
    {
        $response = Http::asForm()->post(config('services.orcid.token_url'), [
            'client_id' => config('services.orcid.client_id'),
            'client_secret' => config('services.orcid.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        if (! $response->successful()) {
            throw new OrcidException("Failed to exchange ORCID authorization code: HTTP {$response->status()}");
        }

        $data = $response->json();

        if (! isset($data['orcid'], $data['access_token'])) {
            throw new OrcidException('ORCID token response is missing orcid or access_token.');
        }

        return new OrcidTokenResult(
            orcid: $data['orcid'],
            accessToken: $data['access_token'],
            expiresIn: (int) ($data['expires_in'] ?? 0),
        );
    }

    /**
     * $user must hold a valid access token for their own ORCID record — this can only ever push
     * a work onto the account that authorized it, never on another contributor's behalf.
     */
    public function pushWork(User $user, Publication $publication): bool
    {
        $journal = $publication->submission->journal;
        $locale = $journal->primary_locale;

        $externalIds = array_values(array_filter([
            $publication->doi ? [
                'external-id-type' => 'doi',
                'external-id-value' => $publication->doi,
                'external-id-relationship' => 'self',
            ] : null,
        ]));

        $work = array_filter([
            'title' => ['title' => ['value' => $publication->title->get($locale, 'en') ?? '']],
            'journal-title' => ['value' => $journal->title->get($locale, 'en') ?? $journal->path],
            'type' => 'journal-article',
            'external-ids' => $externalIds ? ['external-id' => $externalIds] : null,
            'publication-date' => $publication->published_at ? [
                'year' => ['value' => $publication->published_at->format('Y')],
                'month' => ['value' => $publication->published_at->format('m')],
                'day' => ['value' => $publication->published_at->format('d')],
            ] : null,
        ]);

        $response = Http::withToken($user->orcid_access_token)
            ->withHeaders(['Content-Type' => 'application/vnd.orcid+json'])
            ->post(config('services.orcid.api_url')."/{$user->orcid}/works", [
                'bulk' => [['work' => $work]],
            ]);

        return $response->successful();
    }
}
