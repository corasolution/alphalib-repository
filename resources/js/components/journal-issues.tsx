import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, BookOpen, CalendarDays, FileDown } from 'lucide-react';
import { type ReactNode } from 'react';

export interface IssueArticle {
    id: number;
    title: string | null;
    authors: string;
    section?: string | null;
    doi?: string | null;
    galleyId?: number | null;
}

export interface ArchiveIssue {
    id: number;
    identification: string;
    title: string | null;
    articleCount: number;
}

export interface CurrentIssue {
    identification: string;
    title: string | null;
    publishedAt?: string | null;
    articles: IssueArticle[];
}

/** A short gold bar over the heading, the same mark the Blue design puts on its section titles. */
function SectionHeading({ children, aside }: { children: ReactNode; aside?: ReactNode }) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <div className="h-1 w-10 rounded-full bg-amber-400" />
                <h2 className="font-display text-site-primary-deep mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">{children}</h2>
            </div>
            {aside}
        </div>
    );
}

/**
 * What a journal has published: the current issue as a banner over a list of article cards, then
 * the archive of past issues as a grid.
 *
 * Shared by the journal's own homepage and by the site homepage when one journal is shown there,
 * so the two read identically. Colours come from the site-* tokens, so it follows whichever
 * template and palette is active.
 */
export default function JournalIssues({
    journalPath,
    currentIssue,
    archive,
}: {
    journalPath: string;
    currentIssue: CurrentIssue | null;
    archive: ArchiveIssue[];
}) {
    const { t, locale } = useTranslations();

    const publishedOn = currentIssue?.publishedAt
        ? new Date(currentIssue.publishedAt).toLocaleDateString(locale, { year: 'numeric', month: 'long', day: 'numeric' })
        : null;

    return (
        <>
            <section>
                <SectionHeading>{t('journal.current_issue')}</SectionHeading>

                {currentIssue ? (
                    <>
                        <div className="from-site-primary-deep via-site-primary-dark to-site-primary relative overflow-hidden rounded-2xl bg-gradient-to-br p-7 text-white shadow-[0_14px_34px_-16px_rgba(6,42,82,.55)] sm:p-9">
                            {/* The same warm glow and hairlines as the hero, kept faint. */}
                            <div
                                className="pointer-events-none absolute inset-0"
                                style={{
                                    backgroundImage:
                                        'radial-gradient(520px 200px at 90% -20%, rgba(247,148,29,.25), transparent 70%), repeating-linear-gradient(90deg, rgba(255,255,255,.035) 0 1px, transparent 1px 3px)',
                                }}
                            />
                            <div className="relative flex flex-wrap items-end justify-between gap-5">
                                <div className="min-w-0">
                                    <span className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold tracking-widest uppercase">
                                        <span className="size-1.5 rounded-full bg-amber-400" />
                                        {currentIssue.identification}
                                    </span>
                                    {currentIssue.title && (
                                        <p className="font-display mt-4 text-2xl leading-tight font-bold text-balance sm:text-3xl">
                                            {currentIssue.title}
                                        </p>
                                    )}
                                    {publishedOn && (
                                        <p className="text-site-accent mt-3 inline-flex items-center gap-2 text-sm">
                                            <CalendarDays className="size-4" aria-hidden="true" />
                                            {t('journal.published_on', { date: publishedOn })}
                                        </p>
                                    )}
                                </div>
                                <span className="font-display rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold backdrop-blur">
                                    {t('journal.articles', { count: currentIssue.articles.length })}
                                </span>
                            </div>
                        </div>

                        {currentIssue.articles.length === 0 ? (
                            <p className="border-site-border text-site-muted mt-6 rounded-2xl border border-dashed bg-white py-10 text-center">
                                {t('journal.issue_empty')}
                            </p>
                        ) : (
                            <ol className="mt-6 space-y-4">
                                {currentIssue.articles.map((article, index) => (
                                    <li
                                        key={article.id}
                                        className="group border-site-border hover:border-site-primary/40 relative flex items-start gap-4 rounded-2xl border bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-[0_14px_32px_-16px_rgba(6,42,82,.35)] sm:gap-5 sm:p-6"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="font-display text-site-surface-alt group-hover:text-site-primary/25 w-10 shrink-0 text-4xl leading-none font-extrabold tabular-nums transition-colors sm:w-14 sm:text-5xl"
                                        >
                                            {String(index + 1).padStart(2, '0')}
                                        </span>

                                        <div className="min-w-0 flex-1">
                                            {article.section && (
                                                <span className="bg-site-surface-alt text-site-primary-dark font-display inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold tracking-wider uppercase">
                                                    {article.section}
                                                </span>
                                            )}
                                            <h3 className="font-display text-site-ink mt-2 text-lg leading-snug font-semibold sm:text-xl">
                                                {/* A stretched link: the whole card opens the article, while the PDF button below stays separately clickable. */}
                                                <Link
                                                    href={`/journal/${journalPath}/article/${article.id}`}
                                                    className="group-hover:text-site-primary after:absolute after:inset-0 after:content-['']"
                                                >
                                                    {article.title ?? t('journal.untitled')}
                                                </Link>
                                            </h3>
                                            {article.authors && <p className="text-site-body mt-1.5 text-sm">{article.authors}</p>}
                                            {article.doi && <p className="text-site-muted mt-2 text-xs break-all">DOI {article.doi}</p>}
                                        </div>

                                        <div className="relative z-10 flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-center">
                                            {article.galleyId && (
                                                <a
                                                    href={`/journal/${journalPath}/article/${article.id}/galley/${article.galleyId}`}
                                                    className="bg-site-primary hover:bg-site-primary-dark font-display inline-flex items-center gap-1.5 rounded-full px-3.5 py-2 text-xs font-bold tracking-wider text-white uppercase transition-colors"
                                                >
                                                    <FileDown className="size-4" aria-hidden="true" />
                                                    PDF
                                                </a>
                                            )}
                                            <span
                                                aria-hidden="true"
                                                className="bg-site-surface-alt text-site-primary group-hover:bg-site-primary hidden size-9 items-center justify-center rounded-full transition-colors group-hover:text-white sm:flex"
                                            >
                                                <ArrowUpRight className="size-4" />
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </>
                ) : (
                    <div className="border-site-border text-site-body flex flex-col items-center gap-2 rounded-2xl border border-dashed bg-white py-16 text-center">
                        <BookOpen className="text-site-primary/40 size-8" aria-hidden="true" />
                        <p>{t('journal.no_issues')}</p>
                    </div>
                )}
            </section>

            {archive.length > 0 && (
                <section>
                    <SectionHeading>{t('journal.archive')}</SectionHeading>
                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {archive.map((issue) => (
                            <li
                                key={issue.id}
                                className="border-site-border hover:border-site-primary/40 flex flex-col rounded-2xl border border-t-4 border-t-amber-400 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-[0_14px_32px_-16px_rgba(6,42,82,.35)]"
                            >
                                <span className="font-display text-site-primary-dark text-sm font-bold tracking-wide">{issue.identification}</span>
                                {issue.title && (
                                    <span className="font-display text-site-ink mt-2 text-lg leading-snug font-semibold">{issue.title}</span>
                                )}
                                <span className="text-site-muted font-display mt-auto pt-4 text-sm">
                                    {t('journal.articles', { count: issue.articleCount })}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </>
    );
}
