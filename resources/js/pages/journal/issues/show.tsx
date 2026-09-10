import FileDrop from '@/components/file-drop';
import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ChangeEvent, FormEventHandler } from 'react';

interface Galley {
    id: number;
    label: string;
    originalName: string;
}

interface SubmissionRow {
    id: number;
    stage: string;
    title: string;
}

interface Props {
    journal: { path: string; title: string };
    issue: { id: number; identification: string; title: string | null; isPublished: boolean; publishAt: string | null };
    galleys: Galley[];
    submissions: SubmissionRow[];
    can: { manage: boolean };
}

function GalleyUploadForm({ journal, issueId }: { journal: string; issueId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ label: string; file: File | null }>({
        label: '',
        file: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('journal.issues.galleys.store', { journal, issue: issueId }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const onFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        setData('file', e.target.files?.[0] ?? null);
    };

    return (
        <form onSubmit={submit} className="space-y-2 border-t pt-3">
            <div className="grid gap-2">
                <Label htmlFor="label">Format label</Label>
                <Input id="label" placeholder="PDF" value={data.label} onChange={(e) => setData('label', e.target.value)} />
                <InputError message={errors.label} />
            </div>
            <FileDrop value={data.file} onChange={(file) => setData('file', file)} accept=".pdf,.html,.xml" hint="PDF, HTML or XML" />
            <InputError message={errors.file} />
            <Button type="submit" size="sm" disabled={processing || !data.file || !data.label}>
                Upload galley
            </Button>
        </form>
    );
}

export default function IssueShow({ journal, issue, galleys, submissions, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: journal.title, href: route('journal.dashboard', { journal: journal.path }) },
        { title: 'Issues', href: route('journal.issues.index', { journal: journal.path }) },
        { title: issue.identification, href: '#' },
    ];

    const { post: postPublish, processing: publishing } = useForm({});

    const publish: FormEventHandler = (e) => {
        e.preventDefault();

        postPublish(route('journal.issues.publish', { journal: journal.path, issue: issue.id }), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={issue.identification} />
            <PageContainer>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{issue.identification || `Issue #${issue.id}`}</h1>
                        {issue.title && <p className="text-muted-foreground text-sm">{issue.title}</p>}
                    </div>
                    <Badge variant={issue.isPublished ? 'secondary' : 'outline'}>{issue.isPublished ? 'Published' : 'Draft'}</Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Galleys</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {galleys.length === 0 && <p className="text-muted-foreground text-sm">No galleys uploaded yet.</p>}
                        {galleys.map((galley) => (
                            <div key={galley.id} className="flex items-center gap-2 text-sm">
                                <Badge variant="outline">{galley.label}</Badge>
                                <span>{galley.originalName}</span>
                            </div>
                        ))}
                        {can.manage && <GalleyUploadForm journal={journal.path} issueId={issue.id} />}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Articles in this issue</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {submissions.length === 0 && <p className="text-muted-foreground text-sm">No submissions assigned yet.</p>}
                        {submissions.map((submission) => (
                            <div key={submission.id} className="flex items-center justify-between text-sm">
                                <Link
                                    href={route('journal.submissions.show', { journal: journal.path, submission: submission.id })}
                                    className="underline underline-offset-4"
                                >
                                    {submission.title}
                                </Link>
                                <Badge variant="secondary">{submission.stage}</Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {can.manage && !issue.isPublished && (
                    <form onSubmit={publish}>
                        <Button type="submit" disabled={publishing}>
                            Publish issue
                        </Button>
                    </form>
                )}
            </PageContainer>
        </AppLayout>
    );
}
