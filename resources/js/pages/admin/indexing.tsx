import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Circle, FlaskConical, Radio } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Agency {
    configured: boolean;
    live: boolean;
    endpoint: string;
    username: string | null;
    source: 'database' | 'env' | 'none';
    hint: string | null;
    depositorName?: string;
    depositorEmail?: string | null;
}

interface JournalRow {
    path: string;
    title: string;
    enabled: boolean;
    doiPrefix: string | null;
    issnOnline: string | null;
    doajKey: boolean;
}

interface Props {
    crossref: Agency;
    datacite: Agency;
    journals: JournalRow[];
    oaiUrl: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Indexing', href: '#' }];

/** Where a secret came from, so an admin can tell a saved key from an inherited one. */
function SourceNote({ agency }: { agency: Agency }) {
    if (agency.source === 'none') {
        return <span className="text-muted-foreground text-xs">No credentials yet.</span>;
    }

    return (
        <span className="text-muted-foreground text-xs">
            Password {agency.hint} loaded from {agency.source === 'database' ? 'this screen' : 'the server’s .env file'}.
        </span>
    );
}

/**
 * The live/test switch, given more weight than a checkbox deserves on purpose: the test endpoint
 * accepts a deposit and registers nothing, so getting this wrong looks like success for months.
 */
function EndpointSwitch({
    value,
    onChange,
    endpoint,
}: {
    value: boolean;
    onChange: (live: boolean) => void;
    endpoint: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>Endpoint</Label>
            <div className="flex flex-wrap gap-2">
                {[
                    { live: false, label: 'Test', icon: FlaskConical, hint: 'Deposits are accepted and discarded.' },
                    { live: true, label: 'Live', icon: Radio, hint: 'Deposits register real DOIs.' },
                ].map((option) => (
                    <button
                        key={option.label}
                        type="button"
                        onClick={() => onChange(option.live)}
                        aria-pressed={value === option.live}
                        className={
                            'flex flex-1 items-start gap-2.5 rounded-md border p-3 text-left transition-colors ' +
                            (value === option.live ? 'border-primary bg-primary/5' : 'hover:border-muted-foreground/40')
                        }
                    >
                        <option.icon className={'mt-0.5 size-4 shrink-0 ' + (value === option.live ? 'text-primary' : 'text-muted-foreground')} />
                        <span className="grid gap-0.5">
                            <span className="text-sm font-medium">{option.label}</span>
                            <span className="text-muted-foreground text-xs">{option.hint}</span>
                        </span>
                    </button>
                ))}
            </div>
            <p className="text-muted-foreground font-mono text-xs break-all">{endpoint}</p>
        </div>
    );
}

export default function Indexing({ crossref, datacite, journals, oaiUrl }: Props) {
    const form = useForm<Record<string, string | boolean>>({
        crossref_username: crossref.username ?? '',
        crossref_password: '',
        crossref_depositor_name: crossref.depositorName ?? '',
        crossref_depositor_email: crossref.depositorEmail ?? '',
        datacite_username: datacite.username ?? '',
        datacite_password: '',
        crossref_live: crossref.live,
        datacite_live: datacite.live,
    });
    const { data, setData, processing, errors } = form;

    const [result, setResult] = useState<Record<string, { ok: boolean; message: string }>>({});
    const [testing, setTesting] = useState<string | null>(null);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.indexing.update'), { preserveScroll: true });
    };

    const test = async (service: 'crossref' | 'datacite') => {
        setTesting(service);
        try {
            const response = await fetch(route('admin.indexing.test'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ service }),
            });
            const outcome = await response.json();
            setResult((r) => ({ ...r, [service]: outcome }));
        } catch {
            setResult((r) => ({ ...r, [service]: { ok: false, message: 'The test request did not complete.' } }));
        } finally {
            setTesting(null);
        }
    };

    const forget = (service: 'crossref' | 'datacite') =>
        router.delete(route('admin.indexing.forget'), { data: { service }, preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Indexing" />

            <PageContainer>
                <div className="grid gap-1">
                    <h1 className="text-2xl font-semibold">Indexing</h1>
                    <p className="text-muted-foreground max-w-[70ch] text-sm">
                        Credentials for depositing published articles to Crossref and DataCite. Deposits are automatic — every article
                        is sent when an editor publishes it — but only for a journal that has a DOI prefix set in its own settings.
                    </p>
                </div>

                <form onSubmit={submit} className="grid gap-6">
                    {/* ---- Crossref ---- */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                Crossref
                                {crossref.configured ? (
                                    <CheckCircle2 className="size-4 text-emerald-600" aria-label="Configured" />
                                ) : (
                                    <Circle className="text-muted-foreground size-4" aria-label="Not configured" />
                                )}
                            </CardTitle>
                            <CardDescription>
                                The login from your Crossref membership — the account that owns the DOI prefix. Leave the password blank
                                to keep the one already saved.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="crossref_username">Username</Label>
                                    <Input
                                        id="crossref_username"
                                        value={String(data.crossref_username ?? '')}
                                        onChange={(e) => setData('crossref_username', e.target.value)}
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.crossref_username} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="crossref_password">Password</Label>
                                    <Input
                                        id="crossref_password"
                                        type="password"
                                        value={String(data.crossref_password ?? '')}
                                        onChange={(e) => setData('crossref_password', e.target.value)}
                                        placeholder={crossref.configured ? 'Unchanged' : ''}
                                        autoComplete="new-password"
                                    />
                                    <InputError message={errors.crossref_password} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="crossref_depositor_name">Depositor name</Label>
                                    <Input
                                        id="crossref_depositor_name"
                                        value={String(data.crossref_depositor_name ?? '')}
                                        onChange={(e) => setData('crossref_depositor_name', e.target.value)}
                                    />
                                    <InputError message={errors.crossref_depositor_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="crossref_depositor_email">Depositor email</Label>
                                    <Input
                                        id="crossref_depositor_email"
                                        type="email"
                                        value={String(data.crossref_depositor_email ?? '')}
                                        onChange={(e) => setData('crossref_depositor_email', e.target.value)}
                                    />
                                    <p className="text-muted-foreground text-xs">Crossref writes here when a deposit is rejected.</p>
                                    <InputError message={errors.crossref_depositor_email} />
                                </div>
                            </div>

                            <EndpointSwitch
                                value={Boolean(data.crossref_live)}
                                onChange={(live) => setData('crossref_live', live)}
                                endpoint={crossref.endpoint}
                            />

                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="button" variant="outline" size="sm" onClick={() => test('crossref')} disabled={testing === 'crossref'}>
                                    {testing === 'crossref' ? 'Testing…' : 'Test credentials'}
                                </Button>
                                {crossref.source === 'database' && (
                                    <Button type="button" variant="ghost" size="sm" onClick={() => forget('crossref')}>
                                        Remove
                                    </Button>
                                )}
                                <SourceNote agency={crossref} />
                            </div>

                            {result.crossref && (
                                <p className={'text-sm ' + (result.crossref.ok ? 'text-emerald-600' : 'text-destructive')}>
                                    {result.crossref.message}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* ---- DataCite ---- */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                DataCite
                                {datacite.configured ? (
                                    <CheckCircle2 className="size-4 text-emerald-600" aria-label="Configured" />
                                ) : (
                                    <Circle className="text-muted-foreground size-4" aria-label="Not configured" />
                                )}
                            </CardTitle>
                            <CardDescription>
                                An alternative DOI agency. Configure this only if your prefix came from DataCite rather than Crossref —
                                you do not need both.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="datacite_username">Repository ID</Label>
                                    <Input
                                        id="datacite_username"
                                        value={String(data.datacite_username ?? '')}
                                        onChange={(e) => setData('datacite_username', e.target.value)}
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.datacite_username} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="datacite_password">Password</Label>
                                    <Input
                                        id="datacite_password"
                                        type="password"
                                        value={String(data.datacite_password ?? '')}
                                        onChange={(e) => setData('datacite_password', e.target.value)}
                                        placeholder={datacite.configured ? 'Unchanged' : ''}
                                        autoComplete="new-password"
                                    />
                                    <InputError message={errors.datacite_password} />
                                </div>
                            </div>

                            <EndpointSwitch
                                value={Boolean(data.datacite_live)}
                                onChange={(live) => setData('datacite_live', live)}
                                endpoint={datacite.endpoint}
                            />

                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="button" variant="outline" size="sm" onClick={() => test('datacite')} disabled={testing === 'datacite'}>
                                    {testing === 'datacite' ? 'Testing…' : 'Test credentials'}
                                </Button>
                                {datacite.source === 'database' && (
                                    <Button type="button" variant="ghost" size="sm" onClick={() => forget('datacite')}>
                                        Remove
                                    </Button>
                                )}
                                <SourceNote agency={datacite} />
                            </div>

                            {result.datacite && (
                                <p className={'text-sm ' + (result.datacite.ok ? 'text-emerald-600' : 'text-destructive')}>
                                    {result.datacite.message}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing} className="justify-self-start">
                        Save indexing settings
                    </Button>
                </form>

                {/* ---- Readiness ---- */}
                <Card>
                    <CardHeader>
                        <CardTitle>Journal readiness</CardTitle>
                        <CardDescription>
                            A journal without a DOI prefix deposits nothing, however the credentials above are set. Prefix, pattern and
                            ISSN are edited in each journal’s own settings.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[560px] text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b text-left text-xs uppercase">
                                        <th className="py-2 pr-4 font-medium">Journal</th>
                                        <th className="py-2 pr-4 font-medium">DOI prefix</th>
                                        <th className="py-2 pr-4 font-medium">ISSN</th>
                                        <th className="py-2 pr-4 font-medium">DOAJ</th>
                                        <th className="py-2 font-medium">Deposits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {journals.map((journal) => (
                                        <tr key={journal.path} className="border-b last:border-b-0">
                                            <td className="py-2.5 pr-4">
                                                <span className="font-medium">{journal.title}</span>
                                                {!journal.enabled && <span className="text-muted-foreground ml-2 text-xs">(disabled)</span>}
                                            </td>
                                            <td className="py-2.5 pr-4 font-mono text-xs">{journal.doiPrefix ?? '—'}</td>
                                            <td className="py-2.5 pr-4 font-mono text-xs">{journal.issnOnline ?? '—'}</td>
                                            <td className="py-2.5 pr-4 text-xs">{journal.doajKey ? 'Key set' : '—'}</td>
                                            <td className="py-2.5 text-xs">
                                                {journal.doiPrefix ? (
                                                    <span className="text-emerald-600">On publish</span>
                                                ) : (
                                                    <span className="text-muted-foreground inline-flex items-center gap-1">
                                                        <AlertTriangle className="size-3" />
                                                        No prefix
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Harvesting</CardTitle>
                        <CardDescription>
                            Services that harvest rather than receive deposits — WorldCat, BASE, CORE, OpenAIRE — want this address. It
                            needs no credentials and is already serving.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="font-mono text-sm break-all">{oaiUrl}</p>
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
