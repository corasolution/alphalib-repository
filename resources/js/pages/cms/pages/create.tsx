import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { slugify } from '@/lib/slug';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CMS', href: route('cms.index') },
    { title: 'Pages', href: route('cms.pages.index') },
    { title: 'New page', href: '#' },
];

export default function SitePagesCreate() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        slug: '',
        body: '',
    });

    // Stops the moment the author edits the slug themselves. Silently overwriting a slug
    // somebody deliberately chose, just because they went back to fix a typo in the title, is
    // the failure mode this flag exists to prevent.
    const [slugEdited, setSlugEdited] = useState(false);

    const onTitleChange = (title: string) => {
        setData((current) => ({
            ...current,
            title,
            slug: slugEdited ? current.slug : slugify(title),
        }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('cms.pages.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New page" />
            <PageContainer>
                <h1 className="text-2xl font-semibold tracking-tight">New page</h1>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Page content</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input id="title" value={data.title} onChange={(e) => onTitleChange(e.target.value)} required />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">URL slug</Label>
                                <div className="flex items-center gap-1 text-sm">
                                    <span className="text-muted-foreground">/pages/</span>
                                    <Input
                                        id="slug"
                                        value={data.slug}
                                        onChange={(e) => {
                                            setSlugEdited(true);
                                            setData('slug', e.target.value);
                                        }}
                                        placeholder="generated from the title"
                                    />
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    {slugEdited
                                        ? 'You are setting this yourself — it will no longer follow the title.'
                                        : data.title.trim() === ''
                                          ? 'Fills in automatically as you type the title.'
                                          : data.slug === ''
                                            ? 'This title has no Latin characters, so a slug will be generated on save.'
                                            : 'Follows the title. Edit it to set your own.'}
                                </p>
                                <InputError message={errors.slug} />
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
                                Create page
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
