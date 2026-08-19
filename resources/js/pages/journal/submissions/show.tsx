import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { FileText, User } from 'lucide-react';

interface TimelineEntry {
    id: number;
    action: string;
    description: string | null;
    actor: string | null;
    createdAt: string;
}

interface ReviewAssignment {
    id: number;
    reviewer: string | null;
    recommendation: string | null;
    invitedAt: string | null;
    dueAt: string | null;
    respondedAt: string | null;
    declinedAt: string | null;
    completedAt: string | null;
}

interface ReviewRound {
    id: number;
    roundNumber: number;
    status: string | null;
    assignments: ReviewAssignment[];
}

interface Decision {
    id: number;
    decision: string;
    decisionLabel: string;
    targetStage: string;
    actor: string;
    reason: string | null;
    createdAt: string;
    requiresCountersign: boolean;
    countersignedBy: string | null;
}

interface Person {
    userId: number;
    name: string;
    role: string;
    stage: string | null;
}

interface SubmissionFile {
    id: number;
    originalName: string;
    version: number;
    isCurrentManuscript: boolean;
    stage: string;
    uploadedBy: string | null;
    size: number;
    createdAt: string;
}

interface Props {
    submission: {
        id: number;
        stage: string;
        stageLabel: string;
        reviewType: string;
        submittedAt: string | null;
        section: string | null;
        currentManuscript: { id: number; originalName: string; version: number; uploadedBy: string | null } | null;
    };
    timeline: TimelineEntry[];
    reviewRounds: ReviewRound[];
    decisions: Decision[];
    people: Person[];
    files: SubmissionFile[];
    can: {
        recordDecision: boolean;
        publish: boolean;
    };
}

function reviewAssignmentStatus(assignment: ReviewAssignment): string {
    if (assignment.completedAt) return 'Completed';
    if (assignment.declinedAt) return 'Declined';
    if (assignment.respondedAt) return 'Accepted';
    return 'Awaiting response';
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function SubmissionShow({ submission, timeline, reviewRounds, decisions, people, files }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: `Submission #${submission.id}`, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Submission #${submission.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Submission #{submission.id}</h1>
                        <p className="text-muted-foreground text-sm">{submission.section ?? 'No section assigned'}</p>
                    </div>
                    <Badge variant="secondary">{submission.stageLabel}</Badge>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1fr)]">
                    {/* Left: timeline */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Timeline</CardTitle>
                            <CardDescription>Every event, decision, and message, in order.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {timeline.length === 0 && <p className="text-muted-foreground text-sm">No activity yet.</p>}
                            {timeline.map((entry) => (
                                <div key={entry.id} className="border-l-2 pl-3">
                                    <p className="text-sm font-medium">{entry.description ?? entry.action}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {entry.actor ?? 'System'} · {new Date(entry.createdAt).toLocaleString()}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    {/* Centre: current task */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Manuscript</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {submission.currentManuscript ? (
                                    <div className="flex items-center gap-2 text-sm">
                                        <FileText className="text-muted-foreground size-4" />
                                        <span>{submission.currentManuscript.originalName}</span>
                                        <Badge variant="outline">v{submission.currentManuscript.version}</Badge>
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground text-sm">No manuscript uploaded yet.</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Review rounds</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {reviewRounds.length === 0 && <p className="text-muted-foreground text-sm">No review rounds yet.</p>}
                                {reviewRounds.map((round) => (
                                    <div key={round.id}>
                                        <p className="mb-2 text-sm font-medium">Round {round.roundNumber}</p>
                                        <div className="space-y-2">
                                            {round.assignments.map((assignment) => (
                                                <div key={assignment.id} className="flex items-center justify-between rounded-md border p-2 text-sm">
                                                    <span>{assignment.reviewer ?? 'Reviewer (identity hidden)'}</span>
                                                    <div className="flex items-center gap-2">
                                                        {assignment.recommendation && <Badge variant="outline">{assignment.recommendation}</Badge>}
                                                        <Badge variant="secondary">{reviewAssignmentStatus(assignment)}</Badge>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Decisions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {decisions.length === 0 && <p className="text-muted-foreground text-sm">No decisions recorded yet.</p>}
                                {decisions.map((decision) => (
                                    <div key={decision.id} className="rounded-md border p-3 text-sm">
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium">{decision.decisionLabel}</span>
                                            <span className="text-muted-foreground text-xs">{new Date(decision.createdAt).toLocaleString()}</span>
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-xs">By {decision.actor}</p>
                                        {decision.reason && <p className="mt-2">{decision.reason}</p>}
                                        {decision.requiresCountersign && (
                                            <Badge variant={decision.countersignedBy ? 'secondary' : 'destructive'} className="mt-2">
                                                {decision.countersignedBy ? `Countersigned by ${decision.countersignedBy}` : 'Awaiting countersign'}
                                            </Badge>
                                        )}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right: people and files */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">People</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {people.map((person) => (
                                    <div key={`${person.userId}-${person.role}`} className="flex items-center gap-2 text-sm">
                                        <User className="text-muted-foreground size-4" />
                                        <div>
                                            <p>{person.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {person.role}
                                                {person.stage ? ` · ${person.stage}` : ''}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Files</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {files.length === 0 && <p className="text-muted-foreground text-sm">No files yet.</p>}
                                {files.map((file, index) => (
                                    <div key={file.id}>
                                        {index > 0 && <Separator className="my-2" />}
                                        <div className="flex items-center justify-between text-sm">
                                            <div>
                                                <p>{file.originalName}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    v{file.version} · {file.stage} · {formatBytes(file.size)}
                                                </p>
                                            </div>
                                            {file.isCurrentManuscript && <Badge variant="outline">Current</Badge>}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
