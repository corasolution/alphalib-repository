import FileDrop, { MultiFileDrop } from '@/components/file-drop';
import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Check, ChevronDown, Loader2, Sparkles } from 'lucide-react';
import { ChangeEvent, DragEvent, FormEventHandler, useState } from 'react';

interface SectionOption {
    id: number;
    title: string;
}

interface Props {
    journal: {
        path: string;
        title: string;
    };
    sections: SectionOption[];
    /**
     * What this installation can actually accept, not what the journal would like to accept.
     * Anything past the request limit is refused by PHP before the form ever reaches a route,
     * so the number shown to an author has to be the real one.
     */
    uploads: {
        maxFileBytes: number;
        maxFileLabel: string;
        maxPostBytes: number;
        maxPostLabel: string;
    };
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** Which A1 states the author is actually told about. `idle` and `disabled` say nothing at all. */
type AnalysisState = 'idle' | 'reading' | 'done' | 'failed' | 'unreadable' | 'disabled' | 'unsupported';

/**
 * Marks a field the AI filled in, and says so in words rather than only by colour. It disappears
 * the moment the author edits that field, so the badge always means "still exactly what the model
 * proposed" — which is what makes it safe for them to skim rather than re-read everything.
 */
function SuggestedBadge() {
    return (
        <span className="text-primary bg-primary/10 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium">
            <Sparkles className="size-3" />
            Suggested — please check
        </span>
    );
}

/**
 * "Single scrolling screen with a progress rail, not sequential locked steps" (CLAUDE.md §13.1).
 *
 * The rail doubles as the submission checklist the spec asks to "show inline as you go, not as
 * one intimidating wall at step one" — it ticks itself off as the author fills the form, so what
 * is left to do is always visible without ever blocking them from scrolling ahead.
 *
 * AI file-role classification and autosave-as-draft are P5/later features; the author still picks
 * "Your manuscript" vs "Supporting files" themselves, which is the same choice the AI would
 * otherwise pre-fill for them to confirm.
 */
export default function SubmissionsCreate({ journal, sections, uploads }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: journal.title, href: route('journal.dashboard', { journal: journal.path }) },
        { title: 'New submission', href: '#' },
    ];

    const [showMoreDetails, setShowMoreDetails] = useState(false);
    const [dragging, setDragging] = useState(false);

    const [analysis, setAnalysis] = useState<AnalysisState>('idle');
    const [analysisNote, setAnalysisNote] = useState<string | null>(null);
    const [runId, setRunId] = useState<number | null>(null);

    // Which fields still hold exactly what the model proposed. Editing one removes it, so the
    // "suggested" badge can never linger on text the author has since rewritten.
    const [suggested, setSuggested] = useState<Set<string>>(new Set());

    const clearSuggestion = (field: string) =>
        setSuggested((current) => {
            if (!current.has(field)) return current;
            const next = new Set(current);
            next.delete(field);
            return next;
        });

    const { data, setData, post, processing, errors } = useForm<{
        section_id: string;
        title: string;
        abstract: string;
        cover_letter: string;
        manuscript: File | null;
        supporting_files: File[];
    }>({
        section_id: '',
        title: '',
        abstract: '',
        cover_letter: '',
        manuscript: null,
        supporting_files: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Closes the AI improvement loop: did any suggestion survive to submission untouched?
        // Sent before the redirect and deliberately not awaited — feedback is nice to have, and
        // must never be able to delay or block an author's submission.
        if (runId !== null) {
            const accepted = suggested.size > 0;
            void fetch(route('journal.submissions.analyze.feedback', { journal: journal.path }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ run_id: runId, accepted }),
            }).catch(() => undefined);
        }

        post(route('journal.submissions.store', { journal: journal.path }), {
            forceFormData: true,
        });
    };

    /**
     * A1 (CLAUDE.md §6): read the manuscript and offer the author its metadata to confirm.
     *
     * Every failure path here ends in the form simply staying manual. Nothing this function does
     * can prevent a submission — that is the whole reason it is a separate call rather than part
     * of the submit.
     */
    const analyzeManuscript = async (file: File) => {
        setAnalysis('reading');
        setAnalysisNote(null);
        setRunId(null);

        const body = new FormData();
        body.append('manuscript', file);

        try {
            const response = await fetch(route('journal.submissions.analyze', { journal: journal.path }), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body,
            });

            if (!response.ok) {
                setAnalysis('failed');
                return;
            }

            const result = await response.json();

            if (result.status !== 'ok') {
                setAnalysis(result.status as AnalysisState);
                setAnalysisNote(result.message ?? null);
                return;
            }

            const filled = new Set<string>();

            // Only fields this form can actually save are pre-filled. The model also returns
            // authors and keywords, which are logged on the run but deliberately not shown —
            // offering a value the submit endpoint would silently discard is worse than not
            // offering it at all.
            if (result.metadata.title) {
                setData('title', result.metadata.title);
                filled.add('title');
            }

            if (result.metadata.abstract) {
                setData('abstract', result.metadata.abstract);
                filled.add('abstract');
                // Unfold the optional section — a suggestion the author cannot see is a
                // suggestion they cannot check, and they are the one accountable for it.
                setShowMoreDetails(true);
            }

            setSuggested(filled);
            setRunId(result.run_id ?? null);
            setAnalysisNote(result.metadata.extraction_notes ?? null);
            setAnalysis(filled.size > 0 ? 'done' : 'failed');
        } catch {
            setAnalysis('failed');
        }
    };

    const acceptManuscript = (file: File | null) => {
        setData('manuscript', file);
        setSuggested(new Set());
        setRunId(null);
        setAnalysis('idle');

        if (file) void analyzeManuscript(file);
    };

    const onManuscriptChange = (e: ChangeEvent<HTMLInputElement>) => {
        acceptManuscript(e.target.files?.[0] ?? null);
    };

    const onSupportingChange = (e: ChangeEvent<HTMLInputElement>) => {
        setData('supporting_files', Array.from(e.target.files ?? []));
    };

    // The spec leads with "drag files in first", so the drop zone accepts a real drop rather than
    // only proxying a click through to the hidden input.
    const onDrop = (e: DragEvent<HTMLLabelElement>) => {
        e.preventDefault();
        setDragging(false);

        const file = e.dataTransfer.files?.[0];
        if (file) acceptManuscript(file);
    };

    const checklist = [
        { label: 'Manuscript', done: data.manuscript !== null, required: true },
        { label: 'Title', done: data.title.trim().length > 0, required: true },
        { label: 'Section', done: data.section_id !== '', required: sections.length > 0 },
        {
            label: 'Extra details',
            done: data.abstract.trim().length > 0 || data.cover_letter.trim().length > 0 || data.supporting_files.length > 0,
            required: false,
        },
    ].filter((item) => item.required || item.label === 'Extra details');

    const requiredDone = checklist.filter((i) => i.required && i.done).length;
    const requiredTotal = checklist.filter((i) => i.required).length;
    const readyToSubmit = requiredDone === requiredTotal;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${journal.title} — New submission`} />
            <PageContainer>
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">Start a new submission</h1>
                    <p className="text-muted-foreground text-sm">
                        Drop your manuscript in, tell us what it's about, and you're done. Everything else can wait.
                    </p>
                </div>

                <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                    {/* Progress rail — the checklist, ticking itself off as they go. */}
                    <aside className="lg:sticky lg:top-20 lg:w-56 lg:shrink-0">
                        <div className="bg-card rounded-lg border p-4">
                            <p className="text-muted-foreground mb-3 text-xs font-medium tracking-wide uppercase">
                                {requiredDone} of {requiredTotal} required
                            </p>
                            <ol className="space-y-2.5">
                                {checklist.map((item) => (
                                    <li key={item.label} className="flex items-center gap-2.5 text-sm">
                                        <span
                                            className={cn(
                                                'flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors',
                                                item.done
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-muted-foreground/30 text-transparent',
                                            )}
                                        >
                                            <Check className="size-3" strokeWidth={3} />
                                        </span>
                                        <span className={cn(item.done ? 'text-foreground' : 'text-muted-foreground')}>
                                            {item.label}
                                            {!item.required && <span className="text-muted-foreground/70 text-xs"> · optional</span>}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </aside>

                    <form onSubmit={submit} className="min-w-0 flex-1 space-y-5">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Your manuscript</CardTitle>
                                <CardDescription>The article itself — not the figures or cover letter.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FileDrop
                                    value={data.manuscript}
                                    onChange={acceptManuscript}
                                    accept=".pdf,.doc,.docx,.odt"
                                    maxBytes={uploads.maxFileBytes}
                                    label="Drop your manuscript here, or click to browse"
                                    hint={`PDF, DOC, DOCX or ODT — up to ${uploads.maxFileLabel}`}
                                />
                                <InputError message={errors.manuscript} className="mt-2" />

                                {/*
                                  A1 status. `idle` and `disabled` render nothing on purpose: a
                                  journal that has not opted into AI should look like a journal
                                  with no AI, not like one advertising a switched-off feature.
                                */}
                                {analysis === 'reading' && (
                                    <p className="text-muted-foreground mt-3 flex items-center gap-2 text-sm">
                                        <Loader2 className="size-3.5 animate-spin" />
                                        Reading your manuscript to fill in what we can…
                                    </p>
                                )}
                                {analysis === 'done' && (
                                    <div className="border-primary/20 bg-primary/5 mt-3 rounded-md border px-3 py-2">
                                        <p className="text-primary flex items-center gap-2 text-sm font-medium">
                                            <Sparkles className="size-3.5" />
                                            We filled in what we could find
                                        </p>
                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                            Please check every suggested field before submitting — you are the author of record, not the reader that
                                            produced these.
                                            {analysisNote ? ` ${analysisNote}` : ''}
                                        </p>
                                    </div>
                                )}
                                {(analysis === 'failed' || analysis === 'unreadable' || analysis === 'unsupported') && (
                                    <p className="text-muted-foreground mt-3 text-sm">
                                        {analysis === 'unsupported'
                                            ? "We can't read this file type automatically — please fill in the details below yourself."
                                            : (analysisNote ??
                                              "We couldn't read this manuscript automatically — please fill in the details below yourself.")}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">About your article</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <div className="flex items-center gap-2">
                                        <Label htmlFor="title">Title</Label>
                                        {suggested.has('title') && <SuggestedBadge />}
                                    </div>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => {
                                            setData('title', e.target.value);
                                            clearSuggestion('title');
                                        }}
                                        required
                                    />
                                    <InputError message={errors.title} />
                                </div>

                                {sections.length > 0 && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="section_id">Section</Label>
                                        <Select value={data.section_id} onValueChange={(value) => setData('section_id', value)}>
                                            <SelectTrigger id="section_id">
                                                <SelectValue placeholder="Choose a section" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {sections.map((section) => (
                                                    <SelectItem key={section.id} value={String(section.id)}>
                                                        {section.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.section_id} />
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Progressive disclosure — optional metadata stays folded away until asked for. */}
                        <Card>
                            <button
                                type="button"
                                onClick={() => setShowMoreDetails((open) => !open)}
                                aria-expanded={showMoreDetails}
                                className="hover:bg-accent/40 flex w-full items-center justify-between gap-3 rounded-lg px-6 py-4 text-left transition-colors"
                            >
                                <span>
                                    <span className="block text-base font-semibold">Add more details</span>
                                    <span className="text-muted-foreground text-sm">
                                        Abstract, cover letter, figures and supporting data — all optional.
                                    </span>
                                </span>
                                <ChevronDown
                                    className={cn('text-muted-foreground size-4 shrink-0 transition-transform', showMoreDetails && 'rotate-180')}
                                />
                            </button>

                            {showMoreDetails && (
                                <CardContent className="space-y-4 border-t pt-5">
                                    <div className="grid gap-2">
                                        <div className="flex items-center gap-2">
                                            <Label htmlFor="abstract">Abstract</Label>
                                            {suggested.has('abstract') && <SuggestedBadge />}
                                        </div>
                                        <textarea
                                            id="abstract"
                                            className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm shadow-sm"
                                            value={data.abstract}
                                            onChange={(e) => {
                                                setData('abstract', e.target.value);
                                                clearSuggestion('abstract');
                                            }}
                                        />
                                        <InputError message={errors.abstract} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="cover_letter">Cover letter</Label>
                                        <textarea
                                            id="cover_letter"
                                            className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm shadow-sm"
                                            value={data.cover_letter}
                                            onChange={(e) => setData('cover_letter', e.target.value)}
                                        />
                                        <InputError message={errors.cover_letter} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="supporting_files">Figures, tables and supporting data</Label>
                                        <MultiFileDrop
                                            values={data.supporting_files}
                                            onChange={(files) => setData('supporting_files', files)}
                                            maxBytes={uploads.maxFileBytes}
                                            maxTotalBytes={uploads.maxPostBytes - (data.manuscript?.size ?? 0)}
                                            label="Drop figures or data here, or click to browse"
                                            hint={`Up to ${uploads.maxFileLabel} each, ${uploads.maxPostLabel} in total`}
                                        />
                                        <InputError message={errors.supporting_files} />
                                    </div>
                                </CardContent>
                            )}
                        </Card>

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={processing || !readyToSubmit}>
                                Submit
                            </Button>
                            {!readyToSubmit && (
                                <p className="text-muted-foreground text-sm">
                                    Add{' '}
                                    {checklist
                                        .filter((i) => i.required && !i.done)
                                        .map((i) => i.label.toLowerCase())
                                        .join(' and ')}{' '}
                                    to submit.
                                </p>
                            )}
                        </div>
                    </form>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
