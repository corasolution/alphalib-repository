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
import { FormEventHandler, useState } from 'react';

interface ManagerOption {
    id: number;
    name: string;
    email: string;
}

interface Props {
    managers: ManagerOption[];
    locales: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Journals', href: route('admin.journals.index') },
    { title: 'New journal', href: '#' },
];

/** Mirrors the server's `^[a-z0-9][a-z0-9-]*$` rule so the suggested path is always valid. */
function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function AdminJournalsCreate({ managers, locales }: Props) {
    const [pathEdited, setPathEdited] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        title: '',
        path: '',
        primary_locale: locales[0] ?? 'en',
        manager_id: '',
    });

    const onTitleChange = (value: string) => {
        setData((current) => ({
            ...current,
            title: value,
            // Stop overwriting the path the moment the admin types their own.
            path: pathEdited ? current.path : slugify(value),
        }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('admin.journals.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New journal" />
            <PageContainer>
                <h1 className="text-2xl font-semibold tracking-tight">New journal</h1>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Journal details</CardTitle>
                        <CardDescription>
                            The manager you pick can administer the journal straight away. A default &ldquo;Articles&rdquo; section is created so
                            authors can submit immediately.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input id="title" value={data.title} onChange={(e) => onTitleChange(e.target.value)} required />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="path">URL path</Label>
                                <div className="flex items-center gap-1 text-sm">
                                    <span className="text-muted-foreground">/journal/</span>
                                    <Input
                                        id="path"
                                        value={data.path}
                                        onChange={(e) => {
                                            setPathEdited(true);
                                            setData('path', e.target.value);
                                        }}
                                        required
                                    />
                                </div>
                                <p className="text-muted-foreground text-xs">Lowercase letters, numbers and hyphens only.</p>
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

                            <div className="grid gap-2">
                                <Label htmlFor="manager_id">Journal manager</Label>
                                <Select value={data.manager_id} onValueChange={(value) => setData('manager_id', value)}>
                                    <SelectTrigger id="manager_id">
                                        <SelectValue placeholder="Choose who will run this journal" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {managers.map((manager) => (
                                            <SelectItem key={manager.id} value={String(manager.id)}>
                                                {manager.name} ({manager.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">Without a manager, only site admins could administer this journal.</p>
                                <InputError message={errors.manager_id} />
                            </div>

                            <Button type="submit" disabled={processing || !data.manager_id}>
                                Create journal
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
