import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

/**
 * The standard width and padding for a staff screen.
 *
 * Every page previously chose its own cap, ranging from max-w-2xl to max-w-5xl with no pattern —
 * so the CMS forms sat in a 672px column with most of a 1500px window empty beside them, while
 * the submissions list next door ran to 1024px. One component instead of eighteen copies of the
 * same div, so the next page added cannot drift again.
 *
 * Capped at 7xl rather than running edge to edge: a text input spanning a 2560px monitor is
 * unusable, and a card grid with nothing to align to reads as drift rather than layout. 7xl fills
 * a laptop screen and still leaves a margin on a large display.
 */
export default function PageContainer({ className, children }: { className?: string; children: ReactNode }) {
    return <div className={cn('mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6', className)}>{children}</div>;
}
