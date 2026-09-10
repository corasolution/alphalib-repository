import FileDrop from '@/components/file-drop';
import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ChangeEvent, FormEventHandler } from 'react';

interface Highlight {
    title: string;
    body: string;
}

interface JournalOption {
    id: number;
    title: string;
}

interface Props {
    site: {
        title: string;
        tagline: string;
        about: string;
        redirectJournalId: number | null;
        logoUrl: string | null;
    };
    highlights: Highlight[];
    journals: JournalOption[];
}

const NONE = 'none';

export default function CmsHomepage({ site, highlights, journals }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'CMS', href: route('cms.index') },
        { title: 'Homepage', href: '#' },
    ];

    const form = useForm<{
        _method: string;
        title: string;
        tagline: string;
        about: string;
        highlights: Highlight[];
        redirect_journal_id: string;
        logo: File | null;
    }>({
        _method: 'put',
        title: site.title,
        tagline: site.tagline,
        about: site.about,
        highlights,
        redirect_journal_id: site.redirectJournalId ? String(site.redirectJournalId) : NONE,
        logo: null,
    });
    const { data, setData, processing, errors } = form;

    // Replaces one field of one card immutably — mutating data.highlights in place would leave
    // useForm's state identical by reference and React would never re-render the input.
    const setHighlight = (index: number, field: keyof Highlight, value: string) => {
        setData(
            'highlights',
            data.highlights.map((card, i) => (i === index ? { ...card, [field]: value } : card)),
        );
    };
    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // PUT + file upload needs Laravel's POST + _method spoof.
        form.transform((data) => ({
            ...data,
            redirect_journal_id: data.redirect_journal_id === NONE ? '' : data.redirect_journal_id,
        }));
        form.post(route('cms.homepage.update'), { forceFormData: true });
    };

    const onLogoChange = (e: ChangeEvent<HTMLInputElement>) => {
        setData('logo', e.target.files?.[0] ?? null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Homepage — CMS" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Homepage</h1>
                    <p className="text-muted-foreground text-sm">Site identity and the hero text shown at '/'.</p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Identity</CardTitle>
                            <CardDescription>
                                The site title and logo identify the whole system — they appear on the public site, on the sign-in screen, in the
                                staff sidebar and in the browser tab. Tagline and about text are optional and only show up if filled in.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Site title</Label>
                                <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tagline">Tagline</Label>
                                <Input id="tagline" value={data.tagline} onChange={(e) => setData('tagline', e.target.value)} />
                                <InputError message={errors.tagline} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="about">About</Label>
                                <textarea
                                    id="about"
                                    className="border-input bg-background min-h-32 rounded-md border px-3 py-2 text-sm shadow-sm"
                                    value={data.about}
                                    onChange={(e) => setData('about', e.target.value)}
                                />
                                <InputError message={errors.about} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Highlights</CardTitle>
                            <CardDescription>
                                Three numbered cards shown across the homepage. Fill in as many as you want — blank ones are skipped, and clearing all
                                three hides the strip and shows the about text instead.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {data.highlights.map((highlight, index) => (
                                <div key={index} className="grid gap-2 border-l-2 pl-4">
                                    <Label htmlFor={`highlight_title_${index}`} className="text-muted-foreground text-xs tracking-wide uppercase">
                                        Card {index + 1}
                                    </Label>
                                    <Input
                                        id={`highlight_title_${index}`}
                                        placeholder="Heading"
                                        value={highlight.title}
                                        onChange={(e) => setHighlight(index, 'title', e.target.value)}
                                    />
                                    <InputError message={errors[`highlights.${index}.title` as keyof typeof errors]} />
                                    <textarea
                                        id={`highlight_body_${index}`}
                                        placeholder="A sentence or two."
                                        className="border-input bg-background min-h-20 rounded-md border px-3 py-2 text-sm shadow-sm"
                                        value={highlight.body}
                                        onChange={(e) => setHighlight(index, 'body', e.target.value)}
                                    />
                                    <InputError message={errors[`highlights.${index}.body` as keyof typeof errors]} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Logo</CardTitle>
                            <CardDescription>Shown beside the site name everywhere, including the staff sidebar and sign-in screen.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {site.logoUrl && <img src={site.logoUrl} alt="Current logo" className="h-16 w-auto rounded border object-contain" />}
                            <FileDrop
                                value={data.logo}
                                onChange={(file) => setData('logo', file)}
                                accept=".png,.jpg,.jpeg,.svg,.webp"
                                label="Drop a logo here, or click to browse"
                                hint="PNG, JPG, SVG or WebP — up to 2 MB"
                            />
                            <InputError message={errors.logo} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Homepage behaviour</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-2">
                                <Label htmlFor="redirect_journal_id">Redirect '/' straight to a journal</Label>
                                <Select value={data.redirect_journal_id} onValueChange={(value) => setData('redirect_journal_id', value)}>
                                    <SelectTrigger id="redirect_journal_id">
                                        <SelectValue placeholder="No redirect — show the homepage" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>No redirect — show the homepage</SelectItem>
                                        {journals.map((journal) => (
                                            <SelectItem key={journal.id} value={String(journal.id)}>
                                                {journal.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    Useful for a single-journal install — visitors go straight to the journal instead of a site homepage.
                                </p>
                                <InputError message={errors.redirect_journal_id} />
                            </div>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </PageContainer>
        </AppLayout>
    );
}
