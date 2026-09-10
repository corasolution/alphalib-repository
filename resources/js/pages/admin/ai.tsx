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
import { CheckCircle2, CircleSlash, KeyRound, XCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Provider {
    name: string;
    label: string;
    configured: boolean;
    source: 'database' | 'env' | 'none';
    hint: string | null;
}

interface Run {
    id: number;
    feature: string;
    journal: string | null;
    model: string;
    promptVersion: string;
    status: string;
    statusLabel: string;
    inputTokens: number;
    outputTokens: number;
    cents: number;
    latencyMs: number;
    accepted: boolean | null;
    error: string | null;
    createdAt: string | null;
}

interface JournalSpend {
    journal: string;
    runs: number;
    blocked: number;
    failed: number;
    tokens: number;
    cents: number;
}

interface Props {
    providers: Provider[];
    enabled: boolean;
    configuredByEnvOnly: boolean;
    runs: Run[];
    spend: { byJournal: JournalSpend[]; totalRuns: number; totalCents: number; totalTokens: number };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI', href: '#' }];

const money = (cents: number) => (cents >= 100 ? `$${(cents / 100).toFixed(2)}` : `${cents.toFixed(2)}¢`);

/** Credential form for one provider. The stored key is never sent here — only its last four. */
function ProviderCard({ provider }: { provider: Provider }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ provider: provider.name, key: '' });
    const errors = usePage().props.errors as Record<string, string> | undefined;

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.ai.key.update'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('key');
                setOpen(false);
            },
        });
    };

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <KeyRound className="size-4" />
                        {provider.label}
                    </CardTitle>
                    <CardDescription>
                        {provider.configured ? (
                            <>
                                Key ending <code className="font-mono">{provider.hint}</code>
                                {provider.source === 'env' && ' — from the server environment, not editable here'}
                            </>
                        ) : (
                            'No key configured. This provider cannot be used.'
                        )}
                    </CardDescription>
                </div>
                <Badge variant={provider.configured ? 'secondary' : 'outline'} className="gap-1">
                    {provider.configured ? <CheckCircle2 className="size-3" /> : <XCircle className="size-3" />}
                    {provider.configured ? 'Configured' : 'Not set'}
                </Badge>
            </CardHeader>

            <CardContent className="space-y-3">
                {open ? (
                    <form onSubmit={save} className="space-y-3">
                        <div className="grid gap-2">
                            <Label htmlFor={`key_${provider.name}`}>New API key</Label>
                            <Input
                                id={`key_${provider.name}`}
                                type="password"
                                autoComplete="off"
                                value={form.data.key}
                                onChange={(e) => form.setData('key', e.target.value)}
                                placeholder={provider.name === 'anthropic' ? 'sk-ant-…' : 'AIza…'}
                            />
                            <p className="text-muted-foreground text-xs">
                                Stored encrypted. Leave blank and save to remove the stored key and fall back to the server environment.
                            </p>
                            <InputError message={form.errors.key} />
                        </div>
                        <div className="flex items-center gap-2">
                            <Button type="submit" size="sm" disabled={form.processing}>
                                Save key
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                    form.reset('key');
                                    setOpen(false);
                                }}
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>
                ) : (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="button" size="sm" variant="outline" onClick={() => setOpen(true)}>
                            {provider.configured ? 'Replace key' : 'Add key'}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={!provider.configured}
                            onClick={() => router.post(route('admin.ai.test'), { provider: provider.name }, { preserveScroll: true })}
                        >
                            Test connection
                        </Button>
                    </div>
                )}
                {!open && <InputError message={errors?.key} />}
            </CardContent>
        </Card>
    );
}

export default function AdminAi({ providers, enabled, configuredByEnvOnly, runs, spend }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">AI</h1>
                    <p className="text-muted-foreground text-sm">
                        Provider credentials, the installation-wide switch, and every AI call this installation has made.
                    </p>
                </div>

                {/* The switch above every journal's own consent. Both must be on for a call to happen. */}
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                        <div>
                            <CardTitle className="text-base">AI is {enabled ? 'enabled' : 'disabled'} for this installation</CardTitle>
                            <CardDescription>
                                Each journal still has its own consent switch — this one sits above them all. Turning it off stops every AI call
                                immediately, without changing any journal's setting.
                            </CardDescription>
                        </div>
                        <Button
                            type="button"
                            variant={enabled ? 'outline' : 'default'}
                            onClick={() => router.put(route('admin.ai.switch'), { enabled: !enabled }, { preserveScroll: true })}
                        >
                            {enabled ? 'Disable' : 'Enable'}
                        </Button>
                    </CardHeader>
                </Card>

                <div className="grid gap-4 md:grid-cols-2">
                    {providers.map((provider) => (
                        <ProviderCard key={provider.name} provider={provider} />
                    ))}
                </div>

                {configuredByEnvOnly && (
                    <p className="text-muted-foreground rounded-md border border-dashed p-3 text-sm">
                        Keys are currently coming from the server environment. Saving one here stores it encrypted in the database and it takes
                        precedence from the next request.
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Spend</CardTitle>
                        <CardDescription>
                            {spend.totalRuns} run{spend.totalRuns === 1 ? '' : 's'} · {spend.totalTokens.toLocaleString()} tokens ·{' '}
                            {money(spend.totalCents)} total
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {spend.byJournal.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No AI calls recorded yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-muted-foreground border-b text-left text-xs uppercase">
                                        <tr>
                                            <th className="py-2 pr-4 font-medium">Journal</th>
                                            <th className="py-2 pr-4 text-right font-medium">Runs</th>
                                            <th className="py-2 pr-4 text-right font-medium">Blocked</th>
                                            <th className="py-2 pr-4 text-right font-medium">Failed</th>
                                            <th className="py-2 pr-4 text-right font-medium">Tokens</th>
                                            <th className="py-2 text-right font-medium">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {spend.byJournal.map((row) => (
                                            <tr key={row.journal}>
                                                <td className="py-2 pr-4 font-medium">{row.journal}</td>
                                                <td className="py-2 pr-4 text-right">{row.runs}</td>
                                                <td className="text-muted-foreground py-2 pr-4 text-right">{row.blocked || '—'}</td>
                                                <td className="py-2 pr-4 text-right">{row.failed || '—'}</td>
                                                <td className="text-muted-foreground py-2 pr-4 text-right">{row.tokens.toLocaleString()}</td>
                                                <td className="py-2 text-right">{money(row.cents)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recent runs</CardTitle>
                        <CardDescription>
                            Every call, including blocked and failed ones — a run that never left the building is still evidence of what was asked
                            for.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {runs.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Nothing logged yet.</p>
                        ) : (
                            <div className="space-y-2">
                                {runs.map((run) => (
                                    <div key={run.id} className="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-md border p-3 text-sm">
                                        <Badge variant={run.status === 'succeeded' ? 'secondary' : 'outline'} className="gap-1">
                                            {run.status === 'blocked' && <CircleSlash className="size-3" />}
                                            {run.status === 'failed' && <XCircle className="size-3" />}
                                            {run.status === 'succeeded' && <CheckCircle2 className="size-3" />}
                                            {run.statusLabel}
                                        </Badge>
                                        <span className="font-medium">{run.feature}</span>
                                        {run.journal && <span className="text-muted-foreground">/{run.journal}</span>}
                                        <span className="text-muted-foreground font-mono text-xs">{run.model}</span>
                                        <span className="text-muted-foreground text-xs">{run.promptVersion}</span>
                                        <span className="text-muted-foreground ml-auto text-xs">
                                            {run.inputTokens + run.outputTokens > 0 && (
                                                <>
                                                    {(run.inputTokens + run.outputTokens).toLocaleString()} tok · {money(run.cents)} · {run.latencyMs}
                                                    ms ·{' '}
                                                </>
                                            )}
                                            {run.createdAt && new Date(run.createdAt).toLocaleString('en-GB')}
                                        </span>
                                        {run.accepted !== null && (
                                            <span className="w-full text-xs">
                                                {run.accepted ? 'A human kept this output.' : 'A human discarded this output.'}
                                            </span>
                                        )}
                                        {run.error && <span className="text-destructive w-full text-xs">{run.error}</span>}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
