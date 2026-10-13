import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    journal: { path: string; title: string };
    settings: Record<string, string>;
    flags: Record<string, boolean>;
    defaultPattern: string;
}

export default function JournalSettings({ journal, settings, flags, defaultPattern }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: journal.title, href: route('journal.dashboard', { journal: journal.path }) },
        { title: 'Settings', href: '#' },
    ];

    const form = useForm<Record<string, string | boolean>>({ ...settings, ...flags });
    const { data, setData, processing, errors } = form;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('journal.settings.update', { journal: journal.path }), { preserveScroll: true });
    };

    /** The form holds one boolean among the strings; this keeps the text fields typed as text. */
    const text = (key: string) => String(data[key] ?? '');

    // What the pattern will actually produce, so nobody has to save and publish to find out.
    const preview = (text('doi_pattern') || defaultPattern)
        .replace('{prefix}', text('doi_prefix') || '10.XXXX')
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
                                    value={text('publisher')}
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
                                    value={text('issn_online')}
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
                                    value={text('issn_print')}
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
                                    value={text('doi_prefix')}
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
                                    value={text('doi_pattern')}
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

                    <Card>
                        <CardHeader>
                            <CardTitle>Workflow</CardTitle>
                            <CardDescription>How a manuscript travels from submission to peer review.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    checked={Boolean(data.use_screening_stage)}
                                    onCheckedChange={(checked) => setData('use_screening_stage', checked === true)}
                                    className="mt-0.5"
                                />
                                <span>
                                    <span className="font-medium">Screen before peer review</span>
                                    <span className="text-muted-foreground mt-1 block">
                                        Adds a Screening stage between submission and review, for the editor-in-chief’s first look and
                                        the technical and format check. With this on, a manuscript reaches review through screening and
                                        cannot be sent around it. Leave it off and submissions go straight to review as before.
                                    </span>
                                </span>
                            </label>

                            <label className="mt-4 flex items-start gap-3 text-sm">
                                <Checkbox
                                    checked={Boolean(data.require_plagiarism_check)}
                                    onCheckedChange={(checked) => setData('require_plagiarism_check', checked === true)}
                                    className="mt-0.5"
                                />
                                <span>
                                    <span className="font-medium">Require a similarity check before peer review</span>
                                    <span className="text-muted-foreground mt-1 block">
                                        A manuscript cannot be sent to reviewers until a check is recorded as passed. Results are entered
                                        by an editor from whatever tool you licence — the platform stores the outcome and the report, it
                                        does not run the check. Declining or returning a manuscript is never blocked by this.
                                    </span>
                                </span>
                            </label>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>DOAJ</CardTitle>
                            <CardDescription>
                                Once this journal has been accepted into the Directory of Open Access Journals, paste the API key from
                                your DOAJ account here and every article is uploaded automatically when it is published. The key belongs
                                to the journal, not the installation — each accepted journal has its own DOAJ account.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2">
                            <Label htmlFor="doaj_api_key">DOAJ API key</Label>
                            <Input
                                id="doaj_api_key"
                                value={text('doaj_api_key')}
                                onChange={(e) => setData('doaj_api_key', e.target.value)}
                                placeholder="Leave empty until the journal is accepted"
                                autoComplete="off"
                            />
                            <InputError message={errors.doaj_api_key} />
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
