import FileDrop from '@/components/file-drop';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText, User, UserPlus, X } from 'lucide-react';
import { ChangeEvent, FormEventHandler, useState } from 'react';

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
    /** Absent for the author, who is listed here but holds no stage assignment. */
    assignmentId?: number;
    isCurrentStage?: boolean;
}

interface AssignableEditor {
    id: number;
    name: string;
    role: string;
}

interface Galley {
    id: number;
    label: string;
    originalName: string;
    size: string | null;
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

interface LegalDecision {
    value: string;
    label: string;
}

interface AvailableReviewer {
    id: number;
    name: string;
}

interface AvailableIssue {
    id: number;
    identification: string;
}

interface Props {
    journal: string;
    submission: {
        id: number;
        stage: string;
        stageLabel: string;
        returnedToAuthorAt: string | null;
        reviewType: string;
        submittedAt: string | null;
        section: string | null;
        doi: string | null;
        currentManuscript: { id: number; originalName: string; version: number; uploadedBy: string | null } | null;
        issue: string | null;
    };
    timeline: TimelineEntry[];
    reviewRounds: ReviewRound[];
    decisions: Decision[];
    people: Person[];
    canAssignEditors: boolean;
    assignableEditors: AssignableEditor[];
    /** Whether anyone at all holds an editorial role here — an empty picker means two different things. */
    journalHasEditors: boolean;
    canUploadFiles: boolean;
    /** Whether the journal has a registered DOI prefix to generate from. */
    hasDoiPrefix: boolean;
    galleys: Galley[];
    files: SubmissionFile[];
    can: {
        recordDecision: boolean;
        publish: boolean;
        assignReviewer: boolean;
        uploadFile: boolean;
    };
    legalDecisions: LegalDecision[];
    plagiarism: Plagiarism | null;
    availableReviewers: AvailableReviewer[];
    availableIssues: AvailableIssue[];
}

interface PlagiarismCheck {
    id: number;
    status: string;
    statusLabel: string;
    similarityPercent: number | null;
    tool: string | null;
    notes: string | null;
    hasReport: boolean;
    checkedBy: string | null;
    checkedAt: string | null;
}

interface Plagiarism {
    required: boolean;
    statuses: { value: string; label: string }[];
    checks: PlagiarismCheck[];
}
const CHECK_TONE: Record<string, string> = {
    passed: 'border-emerald-300 bg-emerald-50 text-emerald-900',
    flagged: 'border-amber-300 bg-amber-50 text-amber-900',
    pending: 'border-slate-300 bg-slate-50 text-slate-700',
};

/**
 * Similarity screening. Recorded by hand from whatever tool the institution licences — the
 * platform holds the result, not a vendor integration.
 */
function PlagiarismPanel({ submissionId, journal, plagiarism }: { submissionId: number; journal: string; plagiarism: Plagiarism }) {
    const form = useForm<{ status: string; similarity_percent: string; tool: string; notes: string; report: File | null }>({
        status: 'passed',
        similarity_percent: '',
        tool: '',
        notes: '',
        report: null,
    });
    const { data, setData, processing, errors } = form;
    const [open, setOpen] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('journal.submissions.plagiarism.store', { journal, submission: submissionId }), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Similarity</CardTitle>
                <CardDescription>
                    {plagiarism.required
                        ? 'This journal screens before peer review. A check that passed is required before reviewers can be invited.'
                        : 'Optional for this journal. Record a result if you run a check.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {plagiarism.checks.length === 0 && <p className="text-muted-foreground text-sm">No check recorded yet.</p>}

                {plagiarism.checks.map((check) => (
                    <div key={check.id} className="grid gap-1 rounded-md border p-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className={'rounded border px-2 py-0.5 text-xs font-medium ' + (CHECK_TONE[check.status] ?? '')}>
                                {check.statusLabel}
                            </span>
                            {check.similarityPercent !== null && (
                                <span className="text-sm font-medium tabular-nums">{check.similarityPercent}% similarity</span>
                            )}
                            {check.tool && <span className="text-muted-foreground text-xs">{check.tool}</span>}
                        </div>
                        {check.notes && <p className="text-sm">{check.notes}</p>}
                        <p className="text-muted-foreground text-xs">
                            {check.checkedBy ?? 'Unknown'}
                            {check.checkedAt ? ' · ' + new Date(check.checkedAt).toLocaleDateString('en-GB') : ''}
                        </p>
                        {check.hasReport && (
                            <a
                                href={route('journal.submissions.plagiarism.report', {
                                    journal,
                                    submission: submissionId,
                                    plagiarismCheck: check.id,
                                })}
                                className="text-primary text-xs hover:underline"
                            >
                                Open the report
                            </a>
                        )}
                    </div>
                ))}

                {!open ? (
                    <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)} className="justify-self-start">
                        {plagiarism.checks.length > 0 ? 'Record another check' : 'Record a check'}
                    </Button>
                ) : (
                    <form onSubmit={submit} className="grid gap-3 border-t pt-3">
                        <div className="grid gap-2">
                            <Label htmlFor="pc-status">Result</Label>
                            <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                <SelectTrigger id="pc-status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {plagiarism.statuses.map((status) => (
                                        <SelectItem key={status.value} value={status.value}>
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="pc-similarity">Similarity %</Label>
                                <Input
                                    id="pc-similarity"
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={data.similarity_percent}
                                    onChange={(e) => setData('similarity_percent', e.target.value)}
                                />
                                <InputError message={errors.similarity_percent} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="pc-tool">Tool</Label>
                                <Input
                                    id="pc-tool"
                                    value={data.tool}
                                    onChange={(e) => setData('tool', e.target.value)}
                                    placeholder="iThenticate"
                                />
                                <InputError message={errors.tool} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="pc-notes">Notes</Label>
                            <textarea
                                id="pc-notes"
                                className="border-input bg-background min-h-16 rounded-md border px-3 py-2 text-sm shadow-sm"
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder="What the matches actually were."
                            />
                            <InputError message={errors.notes} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="pc-report">Report (optional)</Label>
                            <Input
                                id="pc-report"
                                type="file"
                                accept=".pdf,.html,.htm"
                                onChange={(e) => setData('report', e.target.files?.[0] ?? null)}
                            />
                            <InputError message={errors.report} />
                        </div>

                        <div className="flex gap-2">
                            <Button type="submit" size="sm" disabled={processing}>
                                Save check
                            </Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                )}
            </CardContent>
        </Card>
    );
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

function InviteReviewerForm({
    submissionId,
    journal,
    availableReviewers,
}: {
    submissionId: number;
    journal: string;
    availableReviewers: AvailableReviewer[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        reviewer_id: '',
        due_at: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('journal.submissions.review-assignments.store', { journal, submission: submissionId }), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    if (availableReviewers.length === 0) {
        return <p className="text-muted-foreground text-sm">No users hold the Reviewer role on this journal yet.</p>;
    }

    return (
        <form onSubmit={submit} className="space-y-3">
            <div className="grid gap-2">
                <Label htmlFor="reviewer_id">Reviewer</Label>
                <Select value={data.reviewer_id} onValueChange={(value) => setData('reviewer_id', value)}>
                    <SelectTrigger id="reviewer_id">
                        <SelectValue placeholder="Choose a reviewer" />
                    </SelectTrigger>
                    <SelectContent>
                        {availableReviewers.map((reviewer) => (
                            <SelectItem key={reviewer.id} value={String(reviewer.id)}>
                                {reviewer.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.reviewer_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="due_at">Due date</Label>
                <input
                    id="due_at"
                    type="date"
                    className="border-input bg-background rounded-md border px-3 py-2 text-sm shadow-sm"
                    value={data.due_at}
                    onChange={(e) => setData('due_at', e.target.value)}
                />
                <InputError message={errors.due_at} />
            </div>

            <Button type="submit" size="sm" disabled={processing || !data.reviewer_id}>
                Invite reviewer
            </Button>
        </form>
    );
}

function AssignIssueForm({ submissionId, journal, availableIssues }: { submissionId: number; journal: string; availableIssues: AvailableIssue[] }) {
    const { data, setData, post, processing, errors } = useForm({ issue_id: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('journal.submissions.issue-assignment.store', { journal, submission: submissionId }), { preserveScroll: true });
    };

    if (availableIssues.length === 0) {
        return <p className="text-muted-foreground text-sm">No issues exist yet.</p>;
    }

    return (
        <form onSubmit={submit} className="space-y-2">
            {/* Labelled and given an id: an unlabelled select is unreachable by screen reader. */}
            <Label htmlFor="issue_id" className="sr-only">
                Issue
            </Label>
            <Select value={data.issue_id} onValueChange={(value) => setData('issue_id', value)}>
                <SelectTrigger id="issue_id">
                    <SelectValue placeholder="Choose an issue" />
                </SelectTrigger>
                <SelectContent>
                    {availableIssues.map((issue) => (
                        <SelectItem key={issue.id} value={String(issue.id)}>
                            {issue.identification}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={errors.issue_id} />
            <Button type="submit" size="sm" disabled={processing || !data.issue_id}>
                Assign to issue
            </Button>
        </form>
    );
}

function UploadFileForm({ submissionId, journal }: { submissionId: number; journal: string }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null; as_manuscript: boolean }>({
        file: null,
        as_manuscript: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('journal.submissions.files.store', { journal, submission: submissionId }), {
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
            <FileDrop value={data.file} onChange={(file) => setData('file', file)} accept=".pdf,.doc,.docx,.odt" hint="PDF, DOC, DOCX or ODT" />
            <InputError message={errors.file} />
            <label className="flex items-center gap-2 text-sm">
                <Checkbox checked={data.as_manuscript} onCheckedChange={(checked) => setData('as_manuscript', checked === true)} />
                This replaces the current manuscript
            </label>
            <Button type="submit" size="sm" disabled={processing || !data.file}>
                Upload
            </Button>
        </form>
    );
}

function DecisionForm({ submissionId, journal, legalDecisions }: { submissionId: number; journal: string; legalDecisions: LegalDecision[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        decision: '',
        reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('journal.submissions.decisions.store', { journal, submission: submissionId }), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    if (legalDecisions.length === 0) {
        return <p className="text-muted-foreground text-sm">No decision is currently available for this submission's stage.</p>;
    }

    return (
        <form onSubmit={submit} className="space-y-3">
            <div className="grid gap-2">
                <Label htmlFor="decision">Decision</Label>
                <Select value={data.decision} onValueChange={(value) => setData('decision', value)}>
                    <SelectTrigger id="decision">
                        <SelectValue placeholder="Choose a decision" />
                    </SelectTrigger>
                    <SelectContent>
                        {legalDecisions.map((decision) => (
                            <SelectItem key={decision.value} value={decision.value}>
                                {decision.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.decision} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="reason">Reason (sent to the author)</Label>
                <textarea
                    id="reason"
                    className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm shadow-sm"
                    value={data.reason}
                    onChange={(e) => setData('reason', e.target.value)}
                />
                <InputError message={errors.reason} />
            </div>

            <Button type="submit" disabled={processing || !data.decision}>
                Record decision
            </Button>
        </form>
    );
}

/**
 * Assigns editorial responsibility for the current stage.
 *
 * This is what SubmissionWorkflow::guardStageHasEditor() requires before any decision is allowed.
 * There was no way to do it from the app at all, so every submitted manuscript was stuck at its
 * first decision — the dashboard even counted them under "no editor assigned" with nothing to
 * click.
 */
function AssignEditor({
    journal,
    submissionId,
    editors,
    hasAnyEditor,
}: {
    journal: string;
    submissionId: number;
    editors: AssignableEditor[];
    hasAnyEditor: boolean;
}) {
    const form = useForm({ user_id: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('journal.submissions.editors.store', { journal, submission: submissionId }), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    if (editors.length === 0) {
        // Two very different situations wore the same sentence. "Everyone is already
        // assigned" on a journal with no editors at all is simply false, and it hid the
        // real problem: no decision can be recorded until somebody holds the role, and
        // nothing on this screen said where to grant it.
        return (
            <p className="text-muted-foreground border-t pt-3 text-xs">
                {hasAnyEditor ? (
                    'Everyone with an editor role on this journal is already assigned.'
                ) : (
                    <>
                        Nobody holds an editor role on this journal yet, so no decision can be recorded. Grant one in{' '}
                        <Link href={`/journal/${journal}/people`} className="underline underline-offset-2">
                            Journal &rarr; People
                        </Link>
                        .
                    </>
                )}
            </p>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-2 border-t pt-3">
            <Label htmlFor="assign_editor" className="text-muted-foreground text-xs">
                Assign an editor
            </Label>
            <div className="flex items-center gap-2">
                <Select value={form.data.user_id} onValueChange={(value) => form.setData('user_id', value)}>
                    <SelectTrigger id="assign_editor" className="h-8">
                        <SelectValue placeholder="Choose someone" />
                    </SelectTrigger>
                    <SelectContent>
                        {editors.map((editor) => (
                            <SelectItem key={editor.id} value={String(editor.id)}>
                                {editor.name} · {editor.role}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button type="submit" size="sm" disabled={form.processing || !form.data.user_id}>
                    <UserPlus className="size-4" />
                </Button>
            </div>
            <InputError message={form.errors.user_id} />
        </form>
    );
}
/**
 * The article's DOI — the permanent link a citation points at.
 *
 * Deliberately an explicit editorial act rather than something generated on publish: a DOI is a
 * promise that a URL resolves forever, and the prefix belongs to a real Crossref or DataCite
 * membership. "Generate" only fills the field from the journal's registered prefix; an editor can
 * always paste one issued elsewhere instead.
 */
function DoiCard({
    journal,
    submissionId,
    doi,
    hasPrefix,
    canEdit,
}: {
    journal: string;
    submissionId: number;
    doi: string | null;
    hasPrefix: boolean;
    canEdit: boolean;
}) {
    const form = useForm({ doi: doi ?? '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('journal.submissions.doi.update', { journal, submission: submissionId }), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">DOI</CardTitle>
                <CardDescription>The permanent identifier readers and citation managers resolve.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {!canEdit && <p className="text-muted-foreground text-sm">{doi ?? 'No DOI assigned.'}</p>}

                {canEdit && (
                    <form onSubmit={submit} className="space-y-2">
                        <Input
                            id="doi"
                            value={form.data.doi}
                            onChange={(e) => form.setData('doi', e.target.value)}
                            placeholder="10.5555/journal.v1.i1.a42"
                            className="font-mono text-sm"
                        />
                        <InputError message={form.errors.doi} />
                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="submit" size="sm" disabled={form.processing}>
                                Save
                            </Button>
                            {hasPrefix ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            route('journal.submissions.doi.generate', { journal, submission: submissionId }),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Generate from prefix
                                </Button>
                            ) : (
                                <span className="text-muted-foreground text-xs">
                                    Set a DOI prefix in{' '}
                                    <Link href={route('journal.settings.edit', { journal })} className="underline underline-offset-2">
                                        Journal settings
                                    </Link>{' '}
                                    to generate these automatically.
                                </span>
                            )}
                        </div>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
/**
 * Article galleys — the file a reader actually downloads (CLAUDE.md §7).
 *
 * OJS produces these during Production. Without one, publishing yields a title on the public site
 * with nothing to open, which is most of the point of publishing it.
 */
function GalleysCard({
    journal,
    submissionId,
    galleys,
    canUpload,
}: {
    journal: string;
    submissionId: number;
    galleys: Galley[];
    canUpload: boolean;
}) {
    const form = useForm<{ label: string; file: File | null }>({ label: 'PDF', file: null });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('journal.submissions.galleys.store', { journal, submission: submissionId }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Galleys</CardTitle>
                <CardDescription>The file readers download. Without one, a published article has nothing to open.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {galleys.length === 0 && <p className="text-muted-foreground text-sm">No galley yet.</p>}

                {galleys.map((galley) => (
                    <div key={galley.id} className="flex items-center gap-2 rounded-md border p-2 text-sm">
                        <FileText className="text-muted-foreground size-4 shrink-0" />
                        <span className="min-w-0 flex-1">
                            <span className="block font-medium">{galley.label}</span>
                            <span className="text-muted-foreground block truncate text-xs">
                                {galley.originalName}
                                {galley.size ? ` · ${galley.size}` : ''}
                            </span>
                        </span>
                        {canUpload && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 shrink-0"
                                aria-label={`Remove ${galley.label} galley`}
                                onClick={() =>
                                    router.delete(
                                        route('journal.submissions.galleys.destroy', {
                                            journal,
                                            submission: submissionId,
                                            publicationGalley: galley.id,
                                        }),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <X className="size-3.5" />
                            </Button>
                        )}
                    </div>
                ))}

                {canUpload && (
                    <form onSubmit={submit} className="space-y-2 border-t pt-3">
                        <Label htmlFor="galley_label" className="text-muted-foreground text-xs">
                            Add a galley
                        </Label>
                        <Input
                            id="galley_label"
                            value={form.data.label}
                            onChange={(e) => form.setData('label', e.target.value)}
                            placeholder="PDF"
                            className="h-8"
                        />
                        <FileDrop
                            value={form.data.file}
                            onChange={(file) => form.setData('file', file)}
                            accept=".pdf,.html,.htm,.xml,.epub"
                            hint="PDF, HTML, XML or EPUB"
                        />
                        <InputError message={form.errors.file} />
                        <InputError message={form.errors.label} />
                        <Button type="submit" size="sm" disabled={form.processing || !form.data.file || !form.data.label.trim()}>
                            Upload
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
export default function SubmissionShow({
    journal,
    submission,
    timeline,
    reviewRounds,
    decisions,
    people,
    canAssignEditors,
    assignableEditors,
    journalHasEditors,
    canUploadFiles,
    hasDoiPrefix,
    galleys,
    files,
    can,
    legalDecisions,
    plagiarism,
    availableReviewers,
    availableIssues,
}: Props) {
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
                    <div className="flex items-center gap-2">
                        {/* Custody first: whose move it is matters more to whoever opened this page
                            than which stage it sits at. */}
                        {submission.returnedToAuthorAt && (
                            <Badge variant="outline" className="border-amber-300 bg-amber-50 text-amber-900">
                                With the author since{' '}
                                {new Date(submission.returnedToAuthorAt).toLocaleDateString('en-GB', {
                                    day: 'numeric',
                                    month: 'short',
                                })}
                            </Badge>
                        )}
                        <Badge variant="secondary">{submission.stageLabel}</Badge>
                    </div>
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

                        {(submission.issue || can.recordDecision) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Issue</CardTitle>
                                    <CardDescription>Required before this submission can be published.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {submission.issue ? (
                                        <Badge variant="secondary">{submission.issue}</Badge>
                                    ) : (
                                        can.recordDecision && (
                                            <AssignIssueForm submissionId={submission.id} journal={journal} availableIssues={availableIssues} />
                                        )
                                    )}
                                </CardContent>
                            </Card>
                        )}

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
                                {can.assignReviewer && (
                                    <div className="border-t pt-4">
                                        <p className="mb-2 text-sm font-medium">Invite a reviewer</p>
                                        <InviteReviewerForm submissionId={submission.id} journal={journal} availableReviewers={availableReviewers} />
                                    </div>
                                )}
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

                        {can.recordDecision && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Record a decision</CardTitle>
                                    <CardDescription>Only decisions legal from the current stage are offered.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <DecisionForm submissionId={submission.id} journal={journal} legalDecisions={legalDecisions} />
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Right: people and files */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">People</CardTitle>
                                <CardDescription>A submission cannot move until someone editorial is assigned to its current stage.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {people.map((person) => (
                                    <div
                                        key={`${person.userId}-${person.role}-${person.stage ?? 'none'}`}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <User className="text-muted-foreground size-4 shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate">{person.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {person.role}
                                                {person.stage ? ` · ${person.stage}` : ''}
                                            </p>
                                        </div>
                                        {canAssignEditors && person.assignmentId && person.isCurrentStage && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 shrink-0"
                                                aria-label={`Remove ${person.name}`}
                                                onClick={() =>
                                                    router.delete(
                                                        route('journal.submissions.editors.destroy', {
                                                            journal,
                                                            submission: submission.id,
                                                            stageAssignment: person.assignmentId,
                                                        }),
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                <X className="size-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                ))}

                                {canAssignEditors && (
                                    <AssignEditor
                                        journal={journal}
                                        submissionId={submission.id}
                                        editors={assignableEditors}
                                        hasAnyEditor={journalHasEditors}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <DoiCard
                            journal={journal}
                            submissionId={submission.id}
                            doi={submission.doi}
                            hasPrefix={hasDoiPrefix}
                            canEdit={can.recordDecision}
                        />

                        {plagiarism && (
                            <PlagiarismPanel submissionId={submission.id} journal={journal} plagiarism={plagiarism} />
                        )}

                        <GalleysCard journal={journal} submissionId={submission.id} galleys={galleys} canUpload={canUploadFiles} />

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
                                {can.uploadFile && <UploadFileForm submissionId={submission.id} journal={journal} />}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
