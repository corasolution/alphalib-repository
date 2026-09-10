import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    journal: { path: string; title: string };
    settings: Record<string, string>;
    defaultPattern: string;
}

export default function JournalSettings({ journal, settings, defaultPattern }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: journal.title, href: route('journal.dashboard', { journal: journal.path }) },
        { title: 'Settings', href: '#' },
    ];

    const form = useForm<Record<string, string>>({ ...settings });
    const { data, setData, processing, errors } = form;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('journal.settings.update', { journal: journal.path }), { preserveScroll: true });
    };

    // What the pattern will actually produce, so nobody has to save and publish to find out.
    const preview = (data.doi_pattern || defaultPattern)
        .replace('{prefix}', data.doi_prefix || '10.XXXX')
        .replace('{journalPath}', journal.path)
        .replace('{volume}', '1')
        .replace('{issue}', '1')
        .replace('{articleId}', '42');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${journal.title} — Settings`} />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Journal settings</h1>
                    <p className="text-muted-foreground text-sm">
                        How this journal identifies itself to indexers and citation systems. All of it is optional — the journal works without any of
                        it, and gains it as registrations come through.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Publication identity</CardTitle>
                            <CardDescription>Used by the DOAJ deposit and in the metadata sent to Crossref and DataCite.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor="publisher">Publisher</Label>
                                <Input
                                    id="publisher"
                                    value={data.publisher ?? ''}
                                    onChange={(e) => setData('publisher', e.target.value)}
                                    placeholder="BELTEI International University"
                                />
                                <p className="text-muted-foreground text-xs">The organisation that publishes this journal.</p>
                                <InputError message={errors.publisher} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="issn_online">ISSN (online)</Label>
                                <Input
                                    id="issn_online"
                                    value={data.issn_online ?? ''}
                                    onChange={(e) => setData('issn_online', e.target.value)}
                                    placeholder="2789-1234"
                                    className="font-mono"
                                />
                                <p className="text-muted-foreground text-xs">Issued by your national ISSN centre for the online edition.</p>
                                <InputError message={errors.issn_online} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="issn_print">ISSN (print)</Label>
                                <Input
                                    id="issn_print"
                                    value={data.issn_print ?? ''}
                                    onChange={(e) => setData('issn_print', e.target.value)}
                                    placeholder="2789-5678"
                                    className="font-mono"
                                />
                                <p className="text-muted-foreground text-xs">Only if a printed edition exists. A journal may hold both.</p>
                                <InputError message={errors.issn_print} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">DOIs</CardTitle>
                            <CardDescription>
                                A DOI is a permanent link to an article. The prefix comes from a Crossref or DataCite membership — this app does not
                                issue one, and a made-up prefix resolves to nothing.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <div className="grid gap-1.5 sm:max-w-xs">
                                <Label htmlFor="doi_prefix">DOI prefix</Label>
                                <Input
                                    id="doi_prefix"
                                    value={data.doi_prefix ?? ''}
                                    onChange={(e) => setData('doi_prefix', e.target.value)}
                                    placeholder="10.5555"
                                    className="font-mono"
                                />
                                <p className="text-muted-foreground text-xs">Your registrant code, always starting 10.</p>
                                <InputError message={errors.doi_prefix} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="doi_pattern">DOI pattern</Label>
                                <Input
                                    id="doi_pattern"
                                    value={data.doi_pattern ?? ''}
                                    onChange={(e) => setData('doi_pattern', e.target.value)}
                                    placeholder={defaultPattern}
                                    className="font-mono text-sm"
                                />
                                <p className="text-muted-foreground text-xs">
                                    Leave blank to use the default. Placeholders: <code className="bg-muted rounded px-1">{'{prefix}'}</code>{' '}
                                    <code className="bg-muted rounded px-1">{'{journalPath}'}</code>{' '}
                                    <code className="bg-muted rounded px-1">{'{volume}'}</code>{' '}
                                    <code className="bg-muted rounded px-1">{'{issue}'}</code>{' '}
                                    <code className="bg-muted rounded px-1">{'{articleId}'}</code>
                                </p>
                                <InputError message={errors.doi_pattern} />
                            </div>

                            <div className="bg-muted/40 rounded-md border p-3">
                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">An article would get</p>
                                <p className="mt-1 font-mono text-sm break-all">{preview}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing}>
                        Save settings
                    </Button>
                </form>
            </PageContainer>
        </AppLayout>
    );
}
