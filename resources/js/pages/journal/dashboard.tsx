import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, BookOpen, CheckCircle2, ChevronRight, Clock, Download, FileText, Inbox, Settings, Undo2, Users, UserX } from 'lucide-react';

interface JournalActionQueue {
    needsDecision: number;
    withAuthor: number;
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
    can: { manageUsers: boolean };
}

/**
 * How loud a row is allowed to be. Severity is carried by icon + label + count together,
 * never by color alone — a colourblind reader gets the same ranking from the ordering.
 */
type Severity = 'critical' | 'actionable' | 'waiting';

interface QueueRow {
    key: keyof JournalActionQueue;
    filter: string;
    label: string;
    description: string;
    icon: typeof Inbox;
    severity: Severity;
}

const ROWS: QueueRow[] = [
    {
        key: 'noEditorAssigned',
        filter: 'no_editor_assigned',
        label: 'No editor assigned',
        description: 'New submissions with nobody looking after them yet.',
        icon: UserX,
        severity: 'critical',
    },
    {
        key: 'reviewsOverdue',
        filter: 'reviews_overdue',
        label: 'Reviews overdue',
        description: 'Accepted review assignments past their due date.',
        icon: AlertTriangle,
        severity: 'critical',
    },
    {
        key: 'needsDecision',
        filter: 'needs_decision',
        label: 'Awaiting your decision',
        description: 'Submissions at a stage that needs an editorial decision.',
        icon: Inbox,
        severity: 'actionable',
    },
    {
        key: 'withAuthor',
        filter: 'with_author',
        label: 'With the author',
        description: 'Returned for corrections — waiting on the author, not on you.',
        icon: Undo2,
        severity: 'waiting',
    },
    {
        key: 'readyToPublish',
        filter: 'ready_to_publish',
        label: 'Ready to publish',
        description: 'In production, scheduled to an issue, ready to go.',
        icon: CheckCircle2,
        severity: 'actionable',
    },
    {
        key: 'awaitingReviewerResponse',
        filter: 'awaiting_reviewer_response',
        label: 'Awaiting reviewer response',
        description: 'Review invitations not yet accepted or declined.',
        icon: Clock,
        severity: 'waiting',
    },
];

const SEVERITY_RANK: Record<Severity, number> = { critical: 0, actionable: 1, waiting: 2 };

const ACCENT: Record<Severity, { chip: string; count: string }> = {
    critical: { chip: 'bg-destructive/10 text-destructive', count: 'text-destructive' },
    actionable: { chip: 'bg-primary/10 text-primary', count: 'text-primary' },
    waiting: { chip: 'bg-muted text-muted-foreground', count: 'text-foreground' },
};

export default function JournalDashboard({ journal, queue, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: journal.title,
            href: route('journal.dashboard', { journal: journal.path }),
        },
    ];

    // Anything with work in it floats up, most severe first; empty queues sink and go quiet.
    // That's the whole point of an action queue — the reader shouldn't have to scan five equal
    // boxes to find the one that needs them.
    const rows = [...ROWS].sort((a, b) => {
        const aEmpty = queue[a.key] === 0;
        const bEmpty = queue[b.key] === 0;

        if (aEmpty !== bEmpty) return aEmpty ? 1 : -1;

        return SEVERITY_RANK[a.severity] - SEVERITY_RANK[b.severity];
    });

    const needingAttention = ROWS.filter((row) => queue[row.key] > 0 && row.severity !== 'waiting').length;
    const everythingClear = ROWS.every((row) => queue[row.key] === 0);

    /**
     * Where an editor goes next. Ordered by how often it is wanted, not alphabetically — the
     * submissions list is the daily destination, journal settings the once-a-year one.
     *
     * The last two are Journal Manager work, so they are absent rather than disabled for anyone
     * else: a control that exists only to refuse you is worse than no control.
     */
    const actions = [
        {
            label: 'All submissions',
            description: 'Every manuscript, at any stage',
            href: route('journal.submissions.index', { journal: journal.path }),
            icon: FileText,
        },
        {
            label: 'Issues',
            description: 'Create issues and assign articles',
            href: route('journal.issues.index', { journal: journal.path }),
            icon: BookOpen,
        },
        {
            label: 'Export articles',
            description: 'Published articles as CSV',
            href: route('journal.export.articles-csv', { journal: journal.path }),
            icon: Download,
        },
        ...(can.manageUsers
            ? [
                  {
                      label: 'People',
                      description: 'Editors, reviewers and authors',
                      href: route('journal.people.index', { journal: journal.path }),
                      icon: Users,
                  },
                  {
                      label: 'Journal settings',
                      description: 'Publisher, ISSN and DOI prefix',
                      href: route('journal.settings.edit', { journal: journal.path }),
                      icon: Settings,
                  },
              ]
            : []),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${journal.title} — Dashboard`} />
            <PageContainer>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">{journal.title}</h1>
                        <p className="text-muted-foreground text-sm">
                            {/* Deliberately not a summed total: one submission can sit in several
                                queues at once, so adding the counts would overstate the workload. */}
                            {everythingClear
                                ? 'Nothing needs your attention right now.'
                                : needingAttention > 0
                                  ? `${needingAttention} of ${ROWS.length} queues need your attention.`
                                  : 'Nothing urgent — everything below is just waiting.'}
                        </p>
                    </div>
                    <Button asChild className="shrink-0 self-start">
                        <Link href={route('journal.submissions.create', { journal: journal.path })}>New submission</Link>
                    </Button>
                </div>

                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {rows.map((row, index) => {
                            const count = queue[row.key];
                            const isEmpty = count === 0;
                            const accent = ACCENT[row.severity];
                            const Icon = row.icon;

                            return (
                                <Link
                                    key={row.key}
                                    href={route('journal.submissions.index', { journal: journal.path, filter: row.filter })}
                                    className={cn('hover:bg-accent/60 flex items-center gap-4 px-5 py-4 transition-colors', index > 0 && 'border-t')}
                                >
                                    <span
                                        className={cn(
                                            'flex size-9 shrink-0 items-center justify-center rounded-md',
                                            isEmpty ? 'bg-muted text-muted-foreground/60' : accent.chip,
                                        )}
                                    >
                                        <Icon className="size-4" />
                                    </span>

                                    <span className="min-w-0 flex-1">
                                        <span className={cn('block text-sm font-medium', isEmpty && 'text-muted-foreground')}>{row.label}</span>
                                        <span className="text-muted-foreground block truncate text-xs">{row.description}</span>
                                    </span>

                                    {/* Proportional figures, not tabular — these are standalone
                                        values, not a column that needs to align. */}
                                    <span
                                        className={cn(
                                            'shrink-0 text-right',
                                            isEmpty ? 'text-muted-foreground/60 text-base' : cn('text-2xl font-semibold', accent.count),
                                        )}
                                    >
                                        {count}
                                    </span>

                                    <ChevronRight className={cn('size-4 shrink-0', isEmpty ? 'text-muted-foreground/40' : 'text-muted-foreground')} />
                                </Link>
                            );
                        })}
                    </CardContent>
                </Card>

                {/*
                  Five destinations, previously a single run-on sentence of underlined links —
                  "View all submissions, manage issues, export published articles, manage people,
                  or edit this journal's publisher, ISSN and DOI settings." Readable once, but
                  nothing to aim at and no way to scan for the one you want. Each is now a target
                  with a name and a line saying what it does.
                */}
                <div>
                    <h2 className="text-muted-foreground mb-3 text-xs font-semibold tracking-wider uppercase">Manage this journal</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {actions.map((action) => {
                            const Icon = action.icon;

                            return (
                                <Link
                                    key={action.label}
                                    href={action.href}
                                    className="group bg-card hover:border-primary/50 flex items-start gap-3 rounded-lg border p-4 transition-colors"
                                >
                                    <span className="bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary flex size-9 shrink-0 items-center justify-center rounded-md transition-colors">
                                        <Icon className="size-4" />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block text-sm font-medium">{action.label}</span>
                                        <span className="text-muted-foreground block text-xs leading-relaxed">{action.description}</span>
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
