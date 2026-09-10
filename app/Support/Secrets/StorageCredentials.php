<?php

namespace Alpha\Journal\Support\Secrets;

/**
 * Cloudflare R2 connection details, in sites.storage_credentials.
 *
 * Files land on the local disk until these are filled in. Once they are, new uploads go to the
 * bucket and the existing local files can be migrated across — see MigrateFilesToCloud.
 *
 * Only the key and secret are actually secret; bucket, endpoint, region and the public URL are
 * ordinary configuration. They share the column because a storage connection is useless in
 * pieces — a bucket name with no key configures nothing, and splitting them across two stores
 * would let an admin save half a connection and wonder why nothing moved.
 */
class StorageCredentials extends EncryptedSecrets
{
    public const ACCESS_KEY_ID = 'access_key_id';

    public const SECRET_ACCESS_KEY = 'secret_access_key';

    public const BUCKET = 'bucket';

    public const ENDPOINT = 'endpoint';

    public const REGION = 'region';

    /** Public base URL, when the bucket is served through a custom domain. Optional. */
    public const URL = 'url';

    /** The two that must never be echoed back to a browser. */
    public const SECRET_FIELDS = [self::ACCESS_KEY_ID, self::SECRET_ACCESS_KEY];

    /** Everything an admin screen may show in full. */
    public const PLAIN_FIELDS = [self::BUCKET, self::ENDPOINT, self::REGION, self::URL];

    /**
     * Whether the bucket is usable.
     *
     * The optional URL is deliberately not required: R2 serves signed URLs from the endpoint when
     * no custom domain is set, so demanding one would block a working configuration.
     */
    public function isConfigured(): bool
    {
        return $this->has(self::ACCESS_KEY_ID)
            && $this->has(self::SECRET_ACCESS_KEY)
            && $this->has(self::BUCKET)
            && $this->has(self::ENDPOINT);
    }

    /**
     * The disk definition, ready for config().
     *
     * @return array<string, mixed>
     */
    public function diskConfig(): array
    {
        return [
            'driver' => 's3',
            'key' => $this->for(self::ACCESS_KEY_ID),
            'secret' => $this->for(self::SECRET_ACCESS_KEY),
            'region' => $this->for(self::REGION) ?: 'auto',
            'bucket' => $this->for(self::BUCKET),
            'endpoint' => $this->for(self::ENDPOINT),
            'url' => $this->for(self::URL),
            'use_path_style_endpoint' => true,
            // Surfaces a failed write as an exception instead of a silent false. A migration that
            // reports success while writing nothing is the worst outcome available here.
            'throw' => true,
        ];
    }

    protected function column(): string
    {
        return 'storage_credentials';
    }

    protected function configKeyFor(string $service): string
    {
        return "services.storage.{$service}";
    }
}
