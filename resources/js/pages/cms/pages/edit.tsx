import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    page: {
        id: number;
        slug: string;
        title: string;
        body: string | null;
        layout: string | null;
    };
}

export default function SitePagesEdit({ page }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'CMS', href: route('cms.index') },
        { title: 'Pages', href: route('cms.pages.index') },
        { title: page.title, href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        title: page.title,
        slug: page.slug,
        body: page.body ?? '',
        layout: page.layout ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('cms.pages.update', { page: page.id }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={page.title} />
            <PageContainer>
                <h1 className="text-2xl font-semibold tracking-tight">Edit page</h1>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Page content</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">URL slug</Label>
                                <div className="flex items-center gap-1 text-sm">
                                    <span className="text-muted-foreground">/pages/</span>
                                    <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} required />
                                </div>
                                <InputError message={errors.slug} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="layout">Layout</Label>
                                <select
                                    id="layout"
                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm shadow-sm"
                                    value={data.layout}
                                    onChange={(e) => setData('layout', e.target.value)}
                                >
                                    <option value="">Prose</option>
                                    <option value="editorial_board">Editorial board</option>
                                </select>
                                <p className="text-muted-foreground text-xs">
                                    “Editorial board” renders the groups and people from CMS &rarr; Editorial board beneath the body.
                                </p>
                                <InputError message={errors.layout} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="body">Body</Label>
                                <textarea
                                    id="body"
                                    className="border-input bg-background min-h-64 rounded-md border px-3 py-2 text-sm shadow-sm"
                                    value={data.body}
                                    onChange={(e) => setData('body', e.target.value)}
                                />
                                <InputError message={errors.body} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Save
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
