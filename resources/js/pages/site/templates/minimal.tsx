import PublicSiteLayout from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { type TemplateProps } from './types';

/**
 * Minimal — no imagery, no colour blocks. Large type, generous whitespace, and the journals list
 * as the first thing worth reading.
 *
 * The whole design is restraint, so it uses no hero slides and renders highlights as plain
 * numbered lines rather than cards. Nothing is lost: the images and card styling stay in the CMS
 * and reappear the moment a template that uses them is selected.
 */
export default function MinimalTemplate({ site, menuItems, socialLinks, highlights, journals }: TemplateProps) {
    const { t } = useTranslations();

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            hero={
                <section className="bg-white pt-24 pb-16">
                    <div className="mx-auto max-w-[900px] px-6">
                        <h1 className="text-site-ink font-display text-5xl leading-[1.1] font-bold tracking-tight text-balance sm:text-6xl">
                            {site.title}
                        </h1>
                        {site.tagline && <p className="text-site-muted font-body mt-6 max-w-2xl text-xl">{site.tagline}</p>}
                    </div>
                </section>
            }
        >
            <div className="mx-auto max-w-[900px] px-6 pb-20">
                {site.about && (
                    <section className="border-site-border border-t pt-10">
                        <p className="text-site-body font-body max-w-[46rem] text-xl leading-[1.8] whitespace-pre-line">{site.about}</p>
                    </section>
                )}

                {highlights.length > 0 && (
                    <section className="mt-14 space-y-8">
                        {highlights.map((highlight, index) => (
                            <div key={index} className="flex gap-6">
                                <span className="text-site-border font-display shrink-0 text-3xl font-bold tabular-nums">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <div className="pt-1">
                                    {highlight.title && <h2 className="text-site-ink font-display text-lg font-semibold">{highlight.title}</h2>}
                                    {highlight.body && (
                                        <p className="text-site-body font-body mt-1.5 max-w-[42rem] leading-relaxed">{highlight.body}</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </section>
                )}

                <section className="border-site-border mt-16 border-t pt-10">
                    <h2 className="text-site-muted font-display mb-2 text-xs font-semibold tracking-[0.25em] uppercase">{t('home.journals')}</h2>

                    {journals.length === 0 ? (
                        <p className="text-site-muted font-body py-10">{t('home.no_journals')}</p>
                    ) : (
                        <ul>
                            {journals.map((journal) => (
                                <li key={journal.path} className="border-site-border border-b last:border-b-0">
                                    <Link href={`/journal/${journal.path}`} className="group flex items-center justify-between gap-6 py-6">
                                        <span className="text-site-ink group-hover:text-site-primary font-display text-2xl font-semibold transition-colors">
                                            {journal.title}
                                        </span>
                                        <ArrowRight className="text-site-muted group-hover:text-site-primary size-5 shrink-0 transition-all group-hover:translate-x-1" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </PublicSiteLayout>
    );
}
