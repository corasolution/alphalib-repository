import PageContainer from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, FileSearch } from 'lucide-react';

interface SubmissionRow {
    id: number;
    title: string | null;
    stage: string;
    stageLabel: string;
    section: string | null;
    submittedBy: string | null;
    submittedAt: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    journal: {
        path: string;
        title: string;
    };
    filter: string | null;
    submissions: Paginated<SubmissionRow>;
}

const FILTER_LABELS: Record<string, string> = {
    needs_decision: 'Awaiting your decision',
    reviews_overdue: 'Reviews overdue',
    awaiting_reviewer_response: 'Awaiting reviewer response',
    no_editor_assigned: 'No editor assigned',
    ready_to_publish: 'Ready to publish',
};

/** Stages that are done with; shown quieter so live work stands out in a mixed list. */
const TERMINAL_STAGES = ['published', 'declined'];

function formatDate(value: string | null): string | null {
    if (!value) return null;

    // "19 Aug 2026" rather than a locale-ambiguous 8/19/2026 — this list is read by people in
    // several countries and 8/19 vs 19/8 is a real misread.
    return new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function SubmissionsIndex({ journal, filter, submissions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: journal.title, href: route('journal.dashboard', { journal: journal.path }) },
        { title: 'Submissions', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${journal.title} — Submissions`} />
            <PageContainer className="gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Submissions</h1>
                    <p className="text-muted-foreground text-sm">
                        {submissions.total} {submissions.total === 1 ? 'submission' : 'submissions'}
                        {filter ? ` · ${FILTER_LABELS[filter] ?? filter}` : ' in this journal'}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Link
                        href={route('journal.submissions.index', { journal: journal.path })}
                        className={cn(
                            'rounded-full border px-3 py-1 text-sm transition-colors',
                            filter === null ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-accent text-muted-foreground',
                        )}
                    >
                        All
                    </Link>
                    {Object.entries(FILTER_LABELS).map(([value, label]) => (
                        <Link
                            key={value}
                            href={route('journal.submissions.index', { journal: journal.path, filter: value })}
                            className={cn(
                                'rounded-full border px-3 py-1 text-sm transition-colors',
                                filter === value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-accent text-muted-foreground',
                            )}
                        >
                            {label}
                        </Link>
                    ))}
                </div>

                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {submissions.data.length === 0 ? (
                            <div className="text-muted-foreground flex flex-col items-center gap-2 py-16 text-center">
                                <FileSearch className="size-8 opacity-50" />
                                <p className="text-sm">{filter ? 'No submissions match this filter.' : 'No submissions in this journal yet.'}</p>
                            </div>
                        ) : (
                            submissions.data.map((submission, index) => {
                                const isTerminal = TERMINAL_STAGES.includes(submission.stage);
                                const meta = [
                                    `#${submission.id}`,
                                    submission.submittedBy,
                                    submission.section,
                                    formatDate(submission.submittedAt),
                                ].filter(Boolean);

                                return (
                                    <Link
                                        key={submission.id}
                                        href={route('journal.submissions.show', {
                                            journal: journal.path,
                                            submission: submission.id,
                                        })}
                                        className={cn(
                                            'hover:bg-accent/60 flex items-center gap-4 px-5 py-4 transition-colors',
                                            index > 0 && 'border-t',
                                        )}
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span
                                                className={cn(
                                                    'block truncate font-medium',
                                                    // An untitled submission is a real state — a draft
                                                    // created before its publication record exists.
                                                    submission.title ? 'text-foreground' : 'text-muted-foreground italic',
                                                )}
                                            >
                                                {submission.title ?? 'Untitled submission'}
                                            </span>
                                            <span className="text-muted-foreground mt-0.5 block truncate text-xs">{meta.join(' · ')}</span>
                                        </span>

                                        <Badge variant={isTerminal ? 'outline' : 'secondary'} className="shrink-0">
                                            {submission.stageLabel}
                                        </Badge>

                                        <ChevronRight className="text-muted-foreground size-4 shrink-0" />
                                    </Link>
                                );
                            })
                        )}
                    </CardContent>
                </Card>

                {submissions.last_page > 1 && (
                    <div className="flex flex-wrap gap-1">
                        {submissions.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                className={cn(
                                    'rounded-md border px-3 py-1 text-sm',
                                    link.active ? 'bg-primary text-primary-foreground border-primary' : 'text-foreground hover:bg-accent',
                                    !link.url && 'pointer-events-none opacity-50',
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </PageContainer>
        </AppLayout>
    );
}
