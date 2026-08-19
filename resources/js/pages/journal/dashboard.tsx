import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, Inbox, UserX } from 'lucide-react';

interface JournalActionQueue {
    needsDecision: number;
    reviewsOverdue: number;
    awaitingReviewerResponse: number;
    noEditorAssigned: number;
    readyToPublish: number;
}

interface Props {
    journal: {
        path: string;
        title: string;
    };
    queue: JournalActionQueue;
}

export default function JournalDashboard({ journal, queue }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: journal.title,
            href: route('journal.dashboard', { journal: journal.path }),
        },
    ];

    const cards: Array<{
        key: keyof JournalActionQueue;
        label: string;
        description: string;
        icon: typeof Inbox;
        urgent: boolean;
    }> = [
        {
            key: 'needsDecision',
            label: 'Awaiting your decision',
            description: 'Submissions at a stage that needs an editorial decision.',
            icon: Inbox,
            urgent: queue.needsDecision > 0,
        },
        {
            key: 'reviewsOverdue',
            label: 'Reviews overdue',
            description: 'Accepted review assignments past their due date.',
            icon: AlertTriangle,
            urgent: queue.reviewsOverdue > 0,
        },
        {
            key: 'awaitingReviewerResponse',
            label: 'Awaiting reviewer response',
            description: 'Review invitations not yet accepted or declined.',
            icon: Clock,
            urgent: false,
        },
        {
            key: 'noEditorAssigned',
            label: 'No editor assigned',
            description: 'New submissions with nobody assigned yet.',
            icon: UserX,
            urgent: queue.noEditorAssigned > 0,
        },
        {
            key: 'readyToPublish',
            label: 'Ready to publish',
            description: 'In production, scheduled to an issue, ready to go.',
            icon: CheckCircle2,
            urgent: false,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${journal.title} — Dashboard`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{journal.title}</h1>
                    <p className="text-muted-foreground text-sm">Your editorial queue, ranked by what needs attention.</p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {cards.map((card) => {
                        const Icon = card.icon;
                        const count = queue[card.key];

                        return (
                            <Card key={card.key} className={card.urgent ? 'border-destructive/50' : undefined}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{card.label}</CardTitle>
                                    <Icon className={card.urgent ? 'text-destructive size-4' : 'text-muted-foreground size-4'} />
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-2">
                                        <span className="text-3xl font-bold">{count}</span>
                                        {card.urgent && (
                                            <Badge variant="destructive" className="ml-1">
                                                Needs attention
                                            </Badge>
                                        )}
                                    </div>
                                    <CardDescription className="mt-1">{card.description}</CardDescription>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <div className="text-muted-foreground text-sm">
                    Looking for the full submissions list?{' '}
                    <Link
                        href={route('journal.export.articles-csv', { journal: journal.path })}
                        className="text-foreground underline underline-offset-4"
                    >
                        Export published articles
                    </Link>
                    .
                </div>
            </div>
        </AppLayout>
    );
}
