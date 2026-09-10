<?php

namespace Alpha\Journal\Http\Controllers\Admin;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Storage\FileMigrator;
use Alpha\Journal\Support\Secrets\StorageCredentials;
use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Where uploaded files live, and moving them there (CLAUDE.md §2).
 *
 * An install starts on the server's own disk, which needs no configuration and works offline.
 * Filling in a bucket here sends every *new* upload to it; the files already on disk stay exactly
 * where they are until the migration is run, and each row records which of the two holds its
 * bytes, so both remain readable throughout.
 *
 * The secret and access key are never sent back to the browser — only whether one is set, where
 * it came from, and its last four characters.
 */
class StorageController extends Controller
{
    public function index(StorageCredentials $credentials, FileMigrator $migrator): Response
    {
        Gate::authorize('manage', Site::class);

        return Inertia::render('admin/storage', [
            'configured' => $credentials->isConfigured(),
            'secrets' => collect(StorageCredentials::SECRET_FIELDS)->map(fn (string $field) => [
                'field' => $field,
                'set' => $credentials->has($field),
                'source' => $credentials->source($field),
                'hint' => $credentials->hint($field),
            ])->values(),
            'plain' => collect(StorageCredentials::PLAIN_FIELDS)
                ->mapWithKeys(fn (string $field) => [$field => $credentials->for($field) ?? '']),
            'pending' => $migrator->pendingCount(),
            'writingTo' => app(FileStorage::class)->writeDisk() === FileStorage::CLOUD_DISK ? 'cloud' : 'local',
        ]);
    }

    public function update(Request $request, StorageCredentials $credentials): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            StorageCredentials::ACCESS_KEY_ID => ['nullable', 'string', 'max:255'],
            StorageCredentials::SECRET_ACCESS_KEY => ['nullable', 'string', 'max:255'],
            StorageCredentials::BUCKET => ['nullable', 'string', 'max:255'],
            StorageCredentials::ENDPOINT => ['nullable', 'string', 'max:500', 'url'],
            StorageCredentials::REGION => ['nullable', 'string', 'max:64'],
            StorageCredentials::URL => ['nullable', 'string', 'max:500', 'url'],
        ]);

        foreach ($data as $field => $value) {
            // A blank secret means "leave the stored one alone", not "delete it" — the form never
            // receives the current value, so submitting the page would otherwise wipe the key
            // every time an admin corrected a bucket name.
            if (in_array($field, StorageCredentials::SECRET_FIELDS, true) && blank($value)) {
                continue;
            }

            $credentials->put($field, $value);
        }

        return back()->with('success', 'Storage settings saved.');
    }

    /** Clears one secret outright, which the blank-means-unchanged rule above cannot express. */
    public function forget(Request $request, StorageCredentials $credentials): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'field' => ['required', 'string', 'in:'.implode(',', StorageCredentials::SECRET_FIELDS)],
        ]);

        $credentials->put($data['field'], null);

        return back()->with('success', 'Credential removed.');
    }

    /**
     * Writes a small file to the bucket, reads it back, and deletes it.
     *
     * A round trip rather than a listing: credentials that can list but not write would pass a
     * lighter check and then fail on the first real upload, which is the worst place to find out.
     */
    public function test(StorageCredentials $credentials): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        if (! $credentials->isConfigured()) {
            return back()->withErrors(['storage' => 'Fill in the key, secret, bucket and endpoint first.']);
        }

        $path = 'connectivity-check/'.Str::random(24).'.txt';
        $token = Str::random(32);

        try {
            $disk = Storage::disk(FileStorage::CLOUD_DISK);
            $disk->put($path, $token);

            if ($disk->get($path) !== $token) {
                return back()->withErrors(['storage' => 'The bucket accepted the file but returned something else. Check the bucket name and endpoint.']);
            }

            $disk->delete($path);
        } catch (Throwable $e) {
            return back()->withErrors(['storage' => 'Could not reach the bucket: '.Str::limit($e->getMessage(), 300)]);
        }

        return back()->with('success', 'Wrote, read back and deleted a test file. The bucket works.');
    }

    /**
     * Runs the migration.
     *
     * Synchronous on purpose. This install has QUEUE_CONNECTION=sync, so dispatching a job would
     * run it inside this request anyway while pretending otherwise — and an admin who presses a
     * button wants to be told what happened, not that it has been scheduled. A very large library
     * should use the artisan command, which streams progress and cannot time out a browser.
     */
    public function migrate(StorageCredentials $credentials, FileMigrator $migrator): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        if (! $credentials->isConfigured()) {
            return back()->withErrors(['storage' => 'Configure and test a bucket before migrating.']);
        }

        $result = $migrator->migrate();

        if ($result['failed'] > 0 || $result['missing'] > 0) {
            return back()->withErrors(['storage' => sprintf(
                '%d moved, %d already there. %d failed and %d had no file on disk — those still point at the local disk and are still readable. First problem: %s',
                $result['moved'],
                $result['skipped'],
                $result['failed'],
                $result['missing'],
                $result['errors'][0] ?? 'unknown',
            )]);
        }

        return back()->with('success', sprintf(
            '%d file(s) moved to the bucket, %d were already there. Local copies were kept.',
            $result['moved'],
            $result['skipped'],
        ));
    }
}
