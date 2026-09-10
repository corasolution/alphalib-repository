import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Library, Pencil } from 'lucide-react';

interface JournalRow {
    id: number;
    path: string;
    title: string;
    isEnabled: boolean;
    submissionCount: number;
    publishedCount: number;
}

interface Props {
    journals: JournalRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Journals', href: '#' }];

export default function AdminJournalsIndex({ journals }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Journals" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Journals</h1>
                        <p className="text-muted-foreground text-sm">Every journal on this installation, including disabled ones.</p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.journals.create')}>New journal</Link>
                    </Button>
                </div>

                {journals.length === 0 ? (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex min-h-[30vh] flex-col items-center justify-center gap-2 rounded-xl border text-center">
                        <Library className="text-muted-foreground size-8" />
                        <p className="text-muted-foreground text-sm">No journals yet.</p>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {journals.map((journal) => (
                            <Card key={journal.id}>
                                <CardHeader className="flex flex-row items-start justify-between space-y-0">
                                    <div>
                                        <CardTitle className="text-base font-medium">{journal.title}</CardTitle>
                                        <p className="text-muted-foreground mt-1 text-xs">/journal/{journal.path}</p>
                                    </div>
                                    <Badge variant={journal.isEnabled ? 'secondary' : 'outline'}>{journal.isEnabled ? 'Enabled' : 'Disabled'}</Badge>
                                </CardHeader>
                                <CardContent className="flex items-center justify-between">
                                    <p className="text-muted-foreground text-xs">
                                        {journal.submissionCount} submission{journal.submissionCount === 1 ? '' : 's'} · {journal.publishedCount}{' '}
                                        published
                                    </p>
                                    <div className="flex gap-2">
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={route('journal.dashboard', { journal: journal.path })}>
                                                Dashboard
                                                <ArrowUpRight className="size-3.5" />
                                            </Link>
                                        </Button>
                                        <Button asChild variant="outline" size="icon">
                                            <Link href={route('admin.journals.edit', { journal: journal.id })}>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
