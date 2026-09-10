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
    journal: { path: string; title: string };
}

export default function IssuesCreate({ journal }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: journal.title, href: route('journal.dashboard', { journal: journal.path }) },
        { title: 'Issues', href: route('journal.issues.index', { journal: journal.path }) },
        { title: 'New issue', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        volume: '',
        number: '',
        year: '',
        title: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('journal.issues.store', { journal: journal.path }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New issue" />
            <PageContainer>
                <h1 className="text-2xl font-semibold tracking-tight">New issue</h1>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Issue details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid grid-cols-3 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="volume">Volume</Label>
                                    <Input id="volume" type="number" value={data.volume} onChange={(e) => setData('volume', e.target.value)} />
                                    <InputError message={errors.volume} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="number">Number</Label>
                                    <Input id="number" type="number" value={data.number} onChange={(e) => setData('number', e.target.value)} />
                                    <InputError message={errors.number} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="year">Year</Label>
                                    <Input id="year" type="number" value={data.year} onChange={(e) => setData('year', e.target.value)} />
                                    <InputError message={errors.year} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="title">Title (optional)</Label>
                                <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                                <InputError message={errors.title} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Create issue
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
