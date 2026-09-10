import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    journal: {
        id: number;
        path: string;
        title: string;
        primaryLocale: string;
        isEnabled: boolean;
        aiEnabled: boolean;
    };
    locales: string[];
    /** False when the installation has no Anthropic key or AI is off globally. */
    aiAvailable: boolean;
}

export default function AdminJournalsEdit({ journal, locales, aiAvailable }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Journals', href: route('admin.journals.index') },
        { title: journal.title, href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        title: journal.title,
        path: journal.path,
        primary_locale: journal.primaryLocale,
        is_enabled: journal.isEnabled,
        ai_enabled: journal.aiEnabled,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('admin.journals.update', { journal: journal.id }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${journal.title}`} />
            <PageContainer>
                <h1 className="text-2xl font-semibold tracking-tight">Edit journal</h1>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Journal details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="path">URL path</Label>
                                <div className="flex items-center gap-1 text-sm">
                                    <span className="text-muted-foreground">/journal/</span>
                                    <Input id="path" value={data.path} onChange={(e) => setData('path', e.target.value)} required />
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Lowercase letters, numbers and hyphens only. Changing this breaks existing links to the journal.
                                </p>
                                <InputError message={errors.path} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="primary_locale">Primary language</Label>
                                <Select value={data.primary_locale} onValueChange={(value) => setData('primary_locale', value)}>
                                    <SelectTrigger id="primary_locale">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {locales.map((locale) => (
                                            <SelectItem key={locale} value={locale}>
                                                {locale}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.primary_locale} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Visibility</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    checked={data.is_enabled}
                                    onCheckedChange={(checked) => setData('is_enabled', checked === true)}
                                    className="mt-0.5"
                                />
                                <span>
                                    <span className="font-medium">Enabled</span>
                                    <span className="text-muted-foreground block">
                                        When disabled, the journal disappears from public listings, its public pages return 404, and its articles stop
                                        being harvestable. Editors and site admins keep full access to the editorial dashboard, so work in progress
                                        isn&apos;t lost.
                                    </span>
                                </span>
                            </label>
                            <InputError message={errors.is_enabled} className="mt-2" />
                        </CardContent>
                    </Card>

                    {/*
                      CLAUDE.md §6 guardrail 2. Off by default and opt-in per journal, because what
                      gets processed here are UNPUBLISHED manuscripts — confidential until the
                      journal says otherwise, not merely by the time someone remembers to check.
                    */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">AI assistance</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    checked={data.ai_enabled}
                                    disabled={!aiAvailable}
                                    onCheckedChange={(checked) => setData('ai_enabled', checked === true)}
                                    className="mt-0.5"
                                />
                                <span>
                                    <span className="font-medium">Let AI read manuscripts submitted to this journal</span>
                                    <span className="text-muted-foreground block">
                                        Used to pre-fill the submission form from the uploaded file, so authors confirm details rather than typing
                                        them. AI never makes an editorial decision — every run is logged, and a human confirms or discards everything
                                        it proposes. Leave this off if the journal has not agreed to unpublished manuscripts being processed.
                                    </span>
                                </span>
                            </label>
                            {!aiAvailable && (
                                <p className="text-muted-foreground mt-2 text-xs">
                                    Unavailable: this installation has no AI credentials configured, so the switch has no effect.
                                </p>
                            )}
                            <InputError message={errors.ai_enabled} className="mt-2" />
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
