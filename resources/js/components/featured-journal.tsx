import JournalIssues, { type ArchiveIssue, type CurrentIssue } from '@/components/journal-issues';
import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';

export interface FeaturedJournalData {
    journal: { path: string; title: string };
    currentIssue: CurrentIssue | null;
    archive: ArchiveIssue[];
}

/**
 * One journal's issues shown on the site homepage in place of the list of journals — for a
 * library whose site is, in practice, a single journal. Inherits the active template's palette
 * through the site-* tokens, so it fits every design without being rewritten for each.
 */
export default function FeaturedJournal({ featured }: { featured: FeaturedJournalData }) {
    return (
        <section className="space-y-10">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <h2 className="font-display text-site-primary-dark text-3xl font-bold tracking-tight">{featured.journal.title}</h2>
                <Link
                    href={`/journal/${featured.journal.path}`}
                    className="font-display text-site-primary inline-flex items-center gap-1 text-sm font-semibold hover:underline"
                >
                    {featured.journal.title}
                    <ArrowUpRight className="size-4" aria-hidden="true" />
                </Link>
            </div>
            <JournalIssues journalPath={featured.journal.path} currentIssue={featured.currentIssue} archive={featured.archive} />
        </section>
    );
}
