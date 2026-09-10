import PublicSiteLayout from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { type TemplateProps } from './types';

/**
 * Classic — a printed-journal look: a ruled masthead, a centred statement, and journals set as
 * a typeset list rather than cards.
 *
 * Deliberately ignores hero slides. A masthead is the point of this design, and dropping a
 * photographic banner into it would make it a different template. The images stay in the CMS
 * untouched, so switching to a template that uses them brings them straight back.
 */
export default function ClassicTemplate({ site, menuItems, socialLinks, highlights, journals }: TemplateProps) {
    const { t } = useTranslations();

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            hero={
                <section className="border-site-border border-b-4 border-double bg-white py-14">
                    <div className="mx-auto max-w-[1000px] px-6 text-center">
                        <p className="text-site-muted font-body text-xs tracking-[0.35em] uppercase">Est. {new Date().getFullYear()}</p>
                        <h1 className="text-site-primary-dark font-display mt-4 text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                            {site.title}
                        </h1>
                        {site.tagline && <p className="text-site-body font-body mt-4 text-lg italic">{site.tagline}</p>}
                    </div>
                </section>
            }
        >
            <div className="mx-auto max-w-[1000px] px-6 py-14">
                {site.about && (
                    <section className="border-site-border border-b pb-10 text-center">
                        <p className="text-site-body font-body mx-auto max-w-[46rem] text-lg leading-[1.9] whitespace-pre-line">{site.about}</p>
                    </section>
                )}

                {highlights.length > 0 && (
                    <section className="border-site-border grid gap-8 border-b py-10 sm:grid-cols-3">
                        {highlights.map((highlight, index) => (
                            <div key={index} className="text-center">
                                {highlight.title && (
                                    <h2 className="text-site-primary-dark font-display text-sm font-bold tracking-[0.2em] uppercase">
                                        {highlight.title}
                                    </h2>
                                )}
                                {highlight.body && <p className="text-site-body font-body mt-3 text-sm leading-relaxed">{highlight.body}</p>}
                            </div>
                        ))}
                    </section>
                )}

                <section className="pt-10">
                    <h2 className="text-site-primary-dark font-display mb-6 text-center text-sm font-bold tracking-[0.25em] uppercase">
                        {t('home.journals')}
                    </h2>

                    {journals.length === 0 ? (
                        <div className="text-site-body flex flex-col items-center gap-2 py-12 text-center">
                            <BookOpen className="text-site-border size-8" />
                            <p>{t('home.no_journals')}</p>
                        </div>
                    ) : (
                        <ol className="divide-site-border border-site-border mx-auto max-w-[46rem] divide-y border-y">
                            {journals.map((journal) => (
                                <li key={journal.path}>
                                    <Link
                                        href={`/journal/${journal.path}`}
                                        className="hover:bg-site-surface group flex items-baseline gap-4 px-2 py-5 transition-colors"
                                    >
                                        <span className="text-site-ink group-hover:text-site-primary font-body flex-1 text-lg">{journal.title}</span>
                                        {/* A leader rule, the way a printed table of contents runs the eye across. */}
                                        <span className="border-site-border hidden flex-1 border-b border-dotted sm:block" />
                                        <span className="text-site-muted font-display text-xs tracking-wider uppercase">Read</span>
                                    </Link>
                                </li>
                            ))}
                        </ol>
                    )}

                    <p className="mt-8 text-center">
                        <Link href="/journals" className="text-site-primary font-display text-sm font-semibold hover:underline">
                            {t('home.view_all_journals')}
                        </Link>
                    </p>
                </section>
            </div>
        </PublicSiteLayout>
    );
}
