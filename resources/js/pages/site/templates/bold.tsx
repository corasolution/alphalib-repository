import PublicSiteLayout from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowRight, BookOpen } from 'lucide-react';
import { type TemplateProps } from './types';

/**
 * Bold — full-bleed colour blocks and oversized type, alternating between the primary colour and
 * white so the page reads as a stack of distinct panels.
 *
 * The accent colour carries a lot of weight here, which is exactly why the palette overrides in
 * CMS → Appearance matter most on this template: a university with a strong brand colour gets the
 * biggest visible return from setting it.
 */
export default function BoldTemplate({ site, menuItems, socialLinks, highlights, journals }: TemplateProps) {
    const { t } = useTranslations();

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            hero={
                <section className="bg-site-primary-dark py-24 text-white">
                    <div className="mx-auto max-w-[1200px] px-6">
                        <h1 className="font-display text-5xl leading-[1.05] font-extrabold tracking-tight text-balance sm:text-7xl">{site.title}</h1>
                        {site.tagline && (
                            <p className="text-site-accent font-display mt-6 max-w-3xl text-2xl font-medium sm:text-3xl">{site.tagline}</p>
                        )}
                        <Link
                            href="/journals"
                            className="text-site-primary-dark font-display mt-10 inline-flex items-center gap-2 rounded-full bg-white px-7 py-3.5 text-sm font-bold tracking-wider uppercase transition-transform hover:scale-105"
                        >
                            {t('home.view_all_journals')}
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </section>
            }
        >
            {site.about && (
                <section className="bg-site-accent py-16">
                    <div className="mx-auto max-w-[1200px] px-6">
                        <p className="text-site-ink font-display max-w-4xl text-2xl leading-snug font-semibold whitespace-pre-line">{site.about}</p>
                    </div>
                </section>
            )}

            {highlights.length > 0 && (
                <section className="bg-white py-16">
                    <div className="mx-auto grid max-w-[1200px] gap-px px-6 sm:grid-cols-3">
                        {highlights.map((highlight, index) => (
                            <div key={index} className="bg-site-surface p-8">
                                <span className="text-site-primary font-display block text-5xl font-extrabold tabular-nums">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                {highlight.title && <h2 className="text-site-ink font-display mt-4 text-xl font-bold">{highlight.title}</h2>}
                                {highlight.body && <p className="text-site-body font-body mt-2 leading-relaxed">{highlight.body}</p>}
                            </div>
                        ))}
                    </div>
                </section>
            )}

            <section className="bg-site-primary py-16 text-white">
                <div className="mx-auto max-w-[1200px] px-6">
                    <h2 className="font-display mb-8 text-3xl font-bold">{t('home.journals')}</h2>

                    {journals.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-white/30 py-16 text-center text-white/80">
                            <BookOpen className="size-8" />
                            <p>{t('home.no_journals')}</p>
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {journals.map((journal) => (
                                <Link
                                    key={journal.path}
                                    href={`/journal/${journal.path}`}
                                    className="group hover:text-site-primary-dark flex items-center justify-between gap-4 rounded-xl border-2 border-white/40 p-6 transition-colors hover:bg-white"
                                >
                                    <span className="font-display text-xl font-bold">{journal.title}</span>
                                    <ArrowRight className="size-5 shrink-0 transition-transform group-hover:translate-x-1" />
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </section>
        </PublicSiteLayout>
    );
}
