import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { AlertCircle, FileText, UploadCloud, X } from 'lucide-react';
import { ChangeEvent, DragEvent, useId, useState } from 'react';

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Why an oversized file is caught here rather than server-side: PHP rejects anything past
 * post_max_size inside ValidatePostSize, before a route or a validation rule runs, so the server
 * has no opportunity to answer with a field error. The author gets a bare 413 page and loses
 * everything they typed. Refusing the file before it is sent turns that into a line of text
 * beside the picker. The server still enforces its own limit — this is convenience, not trust.
 */
function tooLarge(file: File, maxBytes?: number): string | null {
    if (!maxBytes || file.size <= maxBytes) return null;

    return `${file.name} is ${formatBytes(file.size)} — the limit is ${formatBytes(maxBytes)}.`;
}

/**
 * The one file picker used across the app.
 *
 * A bare `<input type="file">` renders the browser's own "Choose File / No file chosen" control:
 * unstyled, differently shaped in every browser, and giving no clue what kind of file is wanted
 * or how large it may be. Every upload in this app used one, so uploading a logo looked nothing
 * like uploading a manuscript.
 *
 * The real input stays in the DOM and keeps doing the work — it is wrapped in the label, so
 * clicking anywhere opens the picker and keyboard focus still lands on a real form control.
 * Hiding it with `display: none` would have taken it out of the tab order entirely.
 */
export default function FileDrop({
    value,
    onChange,
    accept,
    maxBytes,
    hint,
    label,
    disabled,
    className,
}: {
    value: File | null;
    onChange: (file: File | null) => void;
    /** Same syntax as the input's accept attribute, e.g. ".pdf,.docx". */
    accept?: string;
    /** Largest file this installation can accept — comes from the server, see UploadLimits. */
    maxBytes?: number;
    /** What is acceptable, in words — "PNG or JPG, up to 2 MB". */
    hint?: string;
    label?: string;
    disabled?: boolean;
    className?: string;
}) {
    const [dragging, setDragging] = useState(false);
    const [rejected, setRejected] = useState<string | null>(null);
    const inputId = useId();

    const take = (file: File | null) => {
        const problem = file ? tooLarge(file, maxBytes) : null;
        setRejected(problem);

        if (!problem) onChange(file);
    };

    const onInputChange = (e: ChangeEvent<HTMLInputElement>) => take(e.target.files?.[0] ?? null);

    const onDrop = (e: DragEvent<HTMLLabelElement>) => {
        e.preventDefault();
        setDragging(false);

        if (disabled) return;

        const file = e.dataTransfer.files?.[0];
        if (file) take(file);
    };

    if (value) {
        return (
            <div className={cn('flex items-center gap-3 rounded-md border p-3', className)}>
                <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-md">
                    <FileText className="size-4" />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium">{value.name}</span>
                    <span className="text-muted-foreground text-xs">{formatBytes(value.size)}</span>
                </span>
                <Button type="button" variant="ghost" size="icon" aria-label="Remove file" onClick={() => onChange(null)}>
                    <X className="size-4" />
                </Button>
            </div>
        );
    }

    return (
        <div className={cn('space-y-1.5', className)}>
            <label
                htmlFor={inputId}
                onDragOver={(e) => {
                    e.preventDefault();
                    if (!disabled) setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={onDrop}
                className={cn(
                    'flex flex-col items-center gap-1 rounded-md border border-dashed px-4 py-6 text-center transition-colors',
                    disabled
                        ? 'cursor-not-allowed opacity-60'
                        : dragging
                          ? 'border-primary bg-primary/5 cursor-pointer'
                          : 'hover:border-primary/50 hover:bg-accent/40 cursor-pointer',
                    rejected && 'border-destructive/60',
                )}
            >
                <UploadCloud className={cn('size-5', dragging ? 'text-primary' : 'text-muted-foreground')} />
                <span className="text-sm font-medium">{label ?? 'Drop a file here, or click to browse'}</span>
                {hint && <span className="text-muted-foreground text-xs">{hint}</span>}
                <input id={inputId} type="file" accept={accept} disabled={disabled} onChange={onInputChange} className="sr-only" />
            </label>

            {rejected && (
                <p role="alert" className="text-destructive flex items-start gap-1.5 text-xs">
                    <AlertCircle className="mt-px size-3.5 shrink-0" />
                    {rejected}
                </p>
            )}
        </div>
    );
}

/**
 * The same control for a set of files — supporting data, figures, that kind of thing.
 *
 * A separate component rather than a `multiple` flag on FileDrop: the value type differs (one
 * File versus an array), and a single component covering both ends up with a union type that
 * every caller has to narrow. Two names, each with an obvious signature, costs less.
 */
export function MultiFileDrop({
    values,
    onChange,
    accept,
    maxBytes,
    maxTotalBytes,
    hint,
    label,
    className,
}: {
    values: File[];
    onChange: (files: File[]) => void;
    accept?: string;
    /** Largest single file. */
    maxBytes?: number;
    /**
     * Largest the whole set may come to. Several files each under the per-file limit can still
     * add up past what PHP will accept for one request, which fails the same unhelpful way.
     */
    maxTotalBytes?: number;
    hint?: string;
    label?: string;
    className?: string;
}) {
    const [dragging, setDragging] = useState(false);
    const [rejected, setRejected] = useState<string | null>(null);
    const inputId = useId();

    // Appends rather than replaces: someone adding a second figure expects to end up with two,
    // not to silently lose the first.
    const add = (incoming: FileList | null) => {
        if (!incoming || incoming.length === 0) return;

        const arriving = Array.from(incoming);

        const oversized = arriving.map((file) => tooLarge(file, maxBytes)).find(Boolean);
        if (oversized) {
            setRejected(oversized);

            return;
        }

        const next = [...values, ...arriving];
        const total = next.reduce((sum, file) => sum + file.size, 0);

        if (maxTotalBytes && total > maxTotalBytes) {
            setRejected(`Those files come to ${formatBytes(total)} together — the limit for one upload is ${formatBytes(maxTotalBytes)}.`);

            return;
        }

        setRejected(null);
        onChange(next);
    };

    return (
        <div className={cn('space-y-2', className)}>
            <label
                htmlFor={inputId}
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    add(e.dataTransfer.files);
                }}
                className={cn(
                    'flex cursor-pointer flex-col items-center gap-1 rounded-md border border-dashed px-4 py-6 text-center transition-colors',
                    dragging ? 'border-primary bg-primary/5' : 'hover:border-primary/50 hover:bg-accent/40',
                    rejected && 'border-destructive/60',
                )}
            >
                <UploadCloud className={cn('size-5', dragging ? 'text-primary' : 'text-muted-foreground')} />
                <span className="text-sm font-medium">{label ?? 'Drop files here, or click to browse'}</span>
                {hint && <span className="text-muted-foreground text-xs">{hint}</span>}
                <input id={inputId} type="file" multiple accept={accept} onChange={(e) => add(e.target.files)} className="sr-only" />
            </label>

            {rejected && (
                <p role="alert" className="text-destructive flex items-start gap-1.5 text-xs">
                    <AlertCircle className="mt-px size-3.5 shrink-0" />
                    {rejected}
                </p>
            )}

            {values.map((file, index) => (
                <div key={`${file.name}-${index}`} className="flex items-center gap-2 rounded-md border p-2 text-sm">
                    <FileText className="text-muted-foreground size-4 shrink-0" />
                    <span className="min-w-0 flex-1 truncate">{file.name}</span>
                    <span className="text-muted-foreground shrink-0 text-xs">{formatBytes(file.size)}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0"
                        aria-label={`Remove ${file.name}`}
                        onClick={() => onChange(values.filter((_, i) => i !== index))}
                    >
                        <X className="size-3.5" />
                    </Button>
                </div>
            ))}
        </div>
    );
}
