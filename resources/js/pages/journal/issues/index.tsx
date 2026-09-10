import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface IssueRow {
    id: number;
    identification: string;
    title: string | null;
    isPublished: boolean;
    galleyCount: number;
    submissionCount: number;
}

interface Props {
    journal: { path: string; title: string };
    issues: IssueRow[];
    can: { manage: boolean };
}

export default function IssuesIndex({ journal, issues, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: journal.title, href: route('journal.dashboard', { journal: journal.path }) },
        { title: 'Issues', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${journal.title} — Issues`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold tracking-tight">Issues</h1>
                    {can.manage && (
                        <Button asChild>
                            <Link href={route('journal.issues.create', { journal: journal.path })}>New issue</Link>
                        </Button>
                    )}
                </div>

                {issues.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No issues yet.</p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {issues.map((issue) => (
                            <Link key={issue.id} href={route('journal.issues.show', { journal: journal.path, issue: issue.id })}>
                                <Card className="hover:bg-accent/50 h-full">
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                        <CardTitle className="text-base">{issue.identification || `Issue #${issue.id}`}</CardTitle>
                                        <Badge variant={issue.isPublished ? 'secondary' : 'outline'}>
                                            {issue.isPublished ? 'Published' : 'Draft'}
                                        </Badge>
                                    </CardHeader>
                                    <CardContent>
                                        {issue.title && <p className="text-sm">{issue.title}</p>}
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {issue.submissionCount} article{issue.submissionCount === 1 ? '' : 's'} · {issue.galleyCount} galley
                                            {issue.galleyCount === 1 ? '' : 's'}
                                        </p>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
