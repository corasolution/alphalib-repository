import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, CloudUpload, HardDrive, Plug, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Secret {
    field: string;
    set: boolean;
    source: 'database' | 'env' | 'none';
    hint: string | null;
}

interface Props {
    configured: boolean;
    secrets: Secret[];
    plain: Record<string, string>;
    /** How many stored files are still only on the server's own disk. */
    pending: number;
    writingTo: 'local' | 'cloud';
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Storage', href: '/admin/storage' }];

const LABELS: Record<string, { label: string; hint: string; placeholder?: string }> = {
    access_key_id: { label: 'Access key ID', hint: 'From the R2 API token you created.' },
    secret_access_key: { label: 'Secret access key', hint: 'Shown once when the token is created.' },
    bucket: { label: 'Bucket', hint: 'The bucket files are written into.', placeholder: 'alpha-journal' },
    endpoint: {
        label: 'Endpoint',
        hint: 'The S3 API endpoint for your account.',
        placeholder: 'https://<account>.r2.cloudflarestorage.com',
    },
    region: { label: 'Region', hint: 'Leave blank for R2 — it uses "auto".', placeholder: 'auto' },
    url: { label: 'Public URL', hint: 'Optional. Only if the bucket is served on your own domain.', placeholder: 'https://files.example.org' },
};

export default function AdminStorage({ configured, secrets, plain, pending, writingTo }: Props) {
    const { errors: pageErrors } = usePage().props as unknown as { errors: Record<string, string> };

    const form = useForm<Record<string, string>>({
        access_key_id: '',
        secret_access_key: '',
        ...plain,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.storage.update'), {
            preserveScroll: true,
            // Only the secrets are cleared. Re-blanking the bucket would make the field look
            // unset on a page that has just saved it.
            onSuccess: () => form.setData((data) => ({ ...data, access_key_id: '', secret_access_key: '' })),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Storage" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Storage</h1>
                    <p className="text-muted-foreground text-sm">
                        Where manuscripts, galleys and images are kept. Files stay on this server until you configure a bucket.
                    </p>
                </div>

                {pageErrors.storage && (
                    <div className="border-destructive/40 bg-destructive/5 text-destructive rounded-lg border p-4 text-sm">{pageErrors.storage}</div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Current destination</CardTitle>
                        <CardDescription>Where a file uploaded right now would be written.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-4">
                        <span className="flex items-center gap-2 text-sm font-medium">
                            {writingTo === 'cloud' ? (
                                <>
                                    <CloudUpload className="text-primary size-5" />
                                    Object storage bucket
                                </>
                            ) : (
                                <>
                                    <HardDrive className="text-muted-foreground size-5" />
                                    This server&apos;s disk
                                </>
                            )}
                        </span>
                        <Badge variant={pending === 0 ? 'secondary' : 'outline'}>
                            {pending === 0 ? 'Nothing waiting to move' : `${pending} file${pending === 1 ? '' : 's'} still on this server`}
                        </Badge>
                    </CardContent>
                </Card>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Bucket connection</CardTitle>
                            <CardDescription>
                                Cloudflare R2, or any S3-compatible bucket. Saving these sends every new upload to the bucket; files already on this
                                server are moved separately, below.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            {secrets.map((secret) => (
                                <div key={secret.field} className="grid gap-1.5">
                                    <Label htmlFor={secret.field}>{LABELS[secret.field].label}</Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id={secret.field}
                                            type="password"
                                            autoComplete="off"
                                            value={form.data[secret.field] ?? ''}
                                            onChange={(e) => form.setData(secret.field, e.target.value)}
                                            placeholder={secret.set ? `Saved ${secret.hint}` : 'Not set'}
                                        />
                                        {secret.set && secret.source === 'database' && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-9 shrink-0"
                                                aria-label={`Remove ${LABELS[secret.field].label}`}
                                                onClick={() =>
                                                    router.delete(route('admin.storage.forget'), {
                                                        data: { field: secret.field },
                                                        preserveScroll: true,
                                                    })
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        {secret.set
                                            ? secret.source === 'env'
                                                ? 'Set in .env. Saving a value here overrides it.'
                                                : 'Saved. Leave blank to keep it unchanged.'
                                            : LABELS[secret.field].hint}
                                    </p>
                                    <InputError message={form.errors[secret.field]} />
                                </div>
                            ))}

                            {Object.keys(plain).map((field) => (
                                <div key={field} className="grid gap-1.5">
                                    <Label htmlFor={field}>{LABELS[field].label}</Label>
                                    <Input
                                        id={field}
                                        value={form.data[field] ?? ''}
                                        onChange={(e) => form.setData(field, e.target.value)}
                                        placeholder={LABELS[field].placeholder}
                                    />
                                    <p className="text-muted-foreground text-xs">{LABELS[field].hint}</p>
                                    <InputError message={form.errors[field]} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Save connection
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!configured}
                            onClick={() => router.post(route('admin.storage.test'), {}, { preserveScroll: true })}
                        >
                            <Plug className="size-4" />
                            Test connection
                        </Button>
                        {configured && (
                            <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                <CheckCircle2 className="size-4 text-emerald-600" />
                                Connection details complete
                            </span>
                        )}
                    </div>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Move existing files</CardTitle>
                        <CardDescription>
                            Copies every file already on this server into the bucket and repoints its record. Safe to run more than once — anything
                            already moved is skipped.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="text-muted-foreground space-y-2 text-sm">
                            {/* Said plainly because it is the question an admin is actually asking before
                                they press a button that touches every manuscript on the system. */}
                            <p>
                                <strong className="text-foreground">Your local copies are kept.</strong> Each file is copied, read back from the
                                bucket and checked against the checksum recorded when it was uploaded, and only then does its record point at the
                                bucket. Nothing is deleted from this server.
                            </p>
                            <p>
                                If it stops partway, the files that have not moved still work — every record knows which disk holds it. Run it again
                                to finish.
                            </p>
                            <p>
                                For a large library, run{' '}
                                <code className="bg-muted rounded px-1 py-0.5 text-xs">php artisan files:migrate-to-cloud</code> instead — it shows
                                progress and cannot time out. Once you are satisfied,{' '}
                                <code className="bg-muted rounded px-1 py-0.5 text-xs">php artisan files:clear-local</code> removes the local copies.
                            </p>
                        </div>

                        <Button
                            type="button"
                            disabled={!configured || pending === 0}
                            onClick={() => router.post(route('admin.storage.migrate'), {}, { preserveScroll: true })}
                        >
                            <CloudUpload className="size-4" />
                            {pending === 0 ? 'Nothing to move' : `Move ${pending} file${pending === 1 ? '' : 's'} to the bucket`}
                        </Button>

                        {!configured && <p className="text-muted-foreground text-xs">Configure and test the bucket connection first.</p>}
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
