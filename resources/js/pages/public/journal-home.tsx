import PublicSiteLayout, { type MenuItem } from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, BookOpen } from 'lucide-react';

interface Article {
    id: number;
    title: string | null;
    authors: string;
}

interface ArchiveIssue {
    id: number;
    identification: string;
    title: string | null;
    articleCount: number;
}

interface Props {
    site: {
        title: string;
        logoUrl: string | null;
        footerText: string | null;
    };
    menuItems: MenuItem[];
    socialLinks: Record<string, string>;
    journal: {
        path: string;
        title: string;
    };
    content: {
        tagline?: string;
        about?: string;
    };
    currentIssue: {
        identification: string;
        title: string | null;
        articles: Article[];
    } | null;
    archive: ArchiveIssue[];
}

/**
 * A journal's public homepage: the same chrome and shape as the site index, focused on what the
 * journal has actually published — the current issue's contents, then the archive.
 *
 * One deliberate absence: there is no marketing furniture (feature cards, vision/mission,
 * "indexed by" strips); the previous version carried three hardcoded selling points that
 * contradicted this app's real-data-or-nothing doctrine outright.
 *
 * Article titles link to the article page, which is where a reader actually reads the thing
 * and downloads the file.
 */
export default function JournalHome({ site, menuItems, socialLinks, journal, content, currentIssue, archive }: Props) {
    const { t } = useTranslations();

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            title={journal.title}
            hero={
                <section className="bg-site-primary-dark relative overflow-hidden py-16 text-white">
                    <div className="from-site-primary-dark to-site-primary-deep absolute inset-0 bg-gradient-to-br opacity-90" />
                    <div className="relative mx-auto max-w-[1200px] px-6 text-center">
                        <h1 className="font-display text-4xl font-bold tracking-tight text-balance sm:text-5xl">{journal.title}</h1>
                        {content.tagline && <p className="font-display text-site-accent mt-4 text-xl italic">{content.tagline}</p>}
                    </div>
                </section>
            }
        >
            <div className="mx-auto max-w-[1200px] space-y-12 px-6 py-16">
                {content.about && (
                    <section className="border-site-border bg-site-surface rounded border p-8">
                        <p className="text-lg leading-relaxed whitespace-pre-line">{content.about}</p>
                    </section>
                )}

                <section>
                    <h2 className="font-display text-site-primary-dark mb-1 text-2xl font-semibold">{t('journal.current_issue')}</h2>
                    {currentIssue ? (
                        <>
                            <p className="font-display text-site-body mb-6">
                                {currentIssue.identification}
                                {currentIssue.title && ` — ${currentIssue.title}`}
                            </p>

                            {currentIssue.articles.length === 0 ? (
                                <p className="border-site-border text-site-muted rounded-xl border border-dashed py-10 text-center">
                                    {t('journal.issue_empty')}
                                </p>
                            ) : (
                                <ol className="divide-site-border border-site-border divide-y rounded-xl border bg-white shadow-sm">
                                    {currentIssue.articles.map((article) => (
                                        <li key={article.id}>
                                            {/*
                                              Now a link: an article used to be a title in a list
                                              with nowhere to go and no file to read.
                                            */}
                                            <Link
                                                href={`/journal/${journal.path}/article/${article.id}`}
                                                className="group hover:bg-site-surface block px-6 py-5 transition-colors"
                                            >
                                                <span className="font-display text-site-ink group-hover:text-site-primary font-semibold">
                                                    {article.title ?? t('journal.untitled')}
                                                </span>
                                                {article.authors && <span className="text-site-body mt-1 block text-sm">{article.authors}</span>}
                                            </Link>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </>
                    ) : (
                        <div className="border-site-border text-site-body flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
                            <BookOpen className="text-site-border size-8" aria-hidden="true" />
                            <p>{t('journal.no_issues')}</p>
                        </div>
                    )}
                </section>

                {archive.length > 0 && (
                    <section>
                        <h2 className="font-display text-site-primary-dark mb-6 text-2xl font-semibold">{t('journal.archive')}</h2>
                        <ul className="divide-site-border border-site-border divide-y rounded-xl border bg-white shadow-sm">
                            {archive.map((issue) => (
                                <li key={issue.id} className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-6 py-4">
                                    <span className="font-display text-site-ink font-semibold">
                                        {issue.identification}
                                        {issue.title && <span className="text-site-body font-normal"> — {issue.title}</span>}
                                    </span>
                                    <span className="font-display text-site-muted text-sm">
                                        {t('journal.articles', { count: issue.articleCount })}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {/*
                  The one call to action kept from the old page. It is how an author actually
                  enters the journal, which is not marketing — unlike the three selling-point
                  cards that used to sit above the articles.
                */}
                <section className="border-site-border flex flex-col items-center justify-center gap-4 border-t pt-10 text-center sm:flex-row">
                    <p className="text-site-body">{t('journal.submit_prompt')}</p>
                    <Link
                        href="/register"
                        className="bg-site-primary-dark font-display hover:bg-site-primary inline-flex items-center gap-1 rounded px-8 py-3 text-sm font-semibold tracking-wider text-white uppercase transition-colors"
                    >
                        {t('journal.register_to_submit')}
                        <ArrowUpRight className="size-4" aria-hidden="true" />
                    </Link>
                </section>
            </div>
        </PublicSiteLayout>
    );
}
