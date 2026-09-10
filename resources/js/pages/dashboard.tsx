import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface JournalSummary {
    path: string;
    title: string;
}

interface Props {
    journals: JournalSummary[];
}

export default function Dashboard({ journals }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Site-admin CMS entry points live in the sidebar's CMS group, not here. */}
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Your journals</h1>
                    <p className="text-muted-foreground text-sm">Pick a journal to see its editorial queue.</p>
                </div>

                {journals.length === 0 ? (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex min-h-[40vh] flex-col items-center justify-center gap-2 rounded-xl border text-center">
                        <BookOpen className="text-muted-foreground size-8" />
                        <p className="text-muted-foreground text-sm">You don&apos;t have a role on any journal yet.</p>
                    </div>
                ) : (
                    <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {journals.map((journal) => (
                            <Link key={journal.path} href={route('journal.dashboard', { journal: journal.path })}>
                                <Card className="hover:bg-accent/50 h-full transition-colors">
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                        <CardTitle className="text-base font-medium">{journal.title}</CardTitle>
                                        <ArrowRight className="text-muted-foreground size-4" />
                                    </CardHeader>
                                    <CardContent>
                                        <CardDescription>Go to editorial dashboard</CardDescription>
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
