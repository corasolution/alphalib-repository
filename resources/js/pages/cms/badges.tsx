import FileDrop from '@/components/file-drop';
import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ImageOff, Plus, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Badge {
    id: number;
    group: string;
    label: string;
    url: string | null;
    logoUrl: string | null;
}

interface Suggestion {
    label: string;
    url: string;
}

interface Props {
    groups: Record<string, string>;
    badges: Record<string, Badge[]>;
    suggestions: Record<string, Suggestion[]>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CMS', href: route('cms.index') },
    { title: 'Indexing & tools', href: '#' },
];

function AddForm({ group, suggestions }: { group: string; suggestions: Suggestion[] }) {
    const form = useForm<{ group: string; label: string; url: string; logo: File | null }>({
        group,
        label: '',
        url: '',
        logo: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('cms.badges.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('label', 'url', 'logo'),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3 border-t pt-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`label_${group}`}>Name</Label>
                    <Input
                        id={`label_${group}`}
                        value={form.data.label}
                        onChange={(e) => form.setData('label', e.target.value)}
                        placeholder="Crossref"
                    />
                    <InputError message={form.errors.label} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`url_${group}`}>Link (optional)</Label>
                    <Input
                        id={`url_${group}`}
                        value={form.data.url}
                        onChange={(e) => form.setData('url', e.target.value)}
                        placeholder="https://www.crossref.org"
                    />
                    <InputError message={form.errors.url} />
                </div>
            </div>

            <FileDrop
                value={form.data.logo}
                onChange={(file) => form.setData('logo', file)}
                accept=".png,.jpg,.jpeg,.svg,.webp"
                label="Drop the logo here, or click to browse"
                hint="PNG, JPG, SVG or WebP — optional, a name-only chip is shown without one"
            />
            <InputError message={form.errors.logo} />

            {/* Names and official links only. The logo file still has to come from the brand —
                each publishes its own asset pack, and their terms differ. */}
            {suggestions.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-muted-foreground text-xs">Quick fill:</span>
                    {suggestions.map((s) => (
                        <button
                            key={s.label}
                            type="button"
                            onClick={() => {
                                form.setData('label', s.label);
                                form.setData('url', s.url);
                            }}
                            className="text-muted-foreground hover:border-primary hover:text-foreground rounded-full border px-2.5 py-1 text-xs transition-colors"
                        >
                            {s.label}
                        </button>
                    ))}
                </div>
            )}

            <Button type="submit" size="sm" disabled={form.processing || form.data.label.trim() === ''}>
                <Plus className="size-4" />
                Add
            </Button>
        </form>
    );
}

function BadgeRow({ badge }: { badge: Badge }) {
    const [replacing, setReplacing] = useState<File | null>(null);

    return (
        <div className="flex items-center gap-3 rounded-md border p-2.5">
            <span className="bg-muted flex size-11 shrink-0 items-center justify-center overflow-hidden rounded">
                {badge.logoUrl ? (
                    <img src={badge.logoUrl} alt="" className="max-h-9 max-w-9 object-contain" />
                ) : (
                    <ImageOff className="text-muted-foreground size-4" />
                )}
            </span>

            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium">{badge.label}</span>
                <span className="text-muted-foreground block truncate text-xs">{badge.url ?? 'No link'}</span>
            </span>

            <label className="text-muted-foreground hover:text-foreground shrink-0 cursor-pointer text-xs underline underline-offset-2">
                {badge.logoUrl ? 'Replace logo' : 'Add logo'}
                <input
                    type="file"
                    accept=".png,.jpg,.jpeg,.svg,.webp"
                    className="sr-only"
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (!file) return;
                        setReplacing(file);
                        router.post(
                            route('cms.badges.update', { badge: badge.id }),
                            { label: badge.label, url: badge.url ?? '', logo: file, _method: 'post' },
                            { forceFormData: true, preserveScroll: true, onFinish: () => setReplacing(null) },
                        );
                    }}
                />
            </label>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8 shrink-0"
                aria-label={`Remove ${badge.label}`}
                disabled={replacing !== null}
                onClick={() => router.delete(route('cms.badges.destroy', { badge: badge.id }), { preserveScroll: true })}
            >
                <X className="size-4" />
            </Button>
        </div>
    );
}

export default function CmsBadges({ groups, badges, suggestions }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Indexing & tools" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Indexing &amp; tools</h1>
                    <p className="text-muted-foreground text-sm">
                        The two strips shown near the bottom of every public page. Both start empty and only show what you add.
                    </p>
                </div>

                {/* Said once, where the decision is actually made. */}
                <div className="rounded-lg border border-amber-300/60 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200">
                    <strong className="font-semibold">Only list services that actually index this journal.</strong> &ldquo;Indexed by&rdquo; tells
                    authors where their work will be findable. Listing a service the journal is not in is the sort of claim that gets a journal
                    flagged as predatory — and it is checkable, so it will be checked.
                </div>

                {Object.entries(groups).map(([key, heading]) => (
                    <Card key={key}>
                        <CardHeader>
                            <CardTitle className="text-base">{heading}</CardTitle>
                            <CardDescription>
                                {key === 'indexed_by'
                                    ? 'Databases and registries that carry this journal’s articles.'
                                    : 'Software authors and reviewers use alongside the journal. Listing a tool is not a claim of endorsement.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {(badges[key] ?? []).length === 0 ? (
                                <p className="text-muted-foreground text-sm">Nothing here yet — this strip is hidden on the public site.</p>
                            ) : (
                                (badges[key] ?? []).map((badge) => <BadgeRow key={badge.id} badge={badge} />)
                            )}

                            <AddForm group={key} suggestions={suggestions[key] ?? []} />
                        </CardContent>
                    </Card>
                ))}
            </PageContainer>
        </AppLayout>
    );
}
