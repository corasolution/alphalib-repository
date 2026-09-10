import PublicSiteLayout from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, BookOpen } from 'lucide-react';
import { type TemplateProps } from './types';

/**
 * Magazine — a large featured block over the first hero image, then journals in an image-led grid.
 *
 * Uses one hero slide rather than a slideshow: a rotating banner competes with the grid beneath
 * it for the same attention, and a magazine cover does not move. Any further slides stay in the
 * CMS for templates that do rotate them.
 */
export default function MagazineTemplate({ site, menuItems, socialLinks, highlights, heroSlides, journals }: TemplateProps) {
    const { t } = useTranslations();
    const cover = heroSlides[0] ?? null;

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            hero={
                <section className="relative min-h-[380px] overflow-hidden sm:min-h-[460px]">
                    {cover ? (
                        <div className="absolute inset-0 bg-cover bg-center" style={{ backgroundImage: `url(${cover.url})` }} />
                    ) : (
                        <div className="bg-site-primary-dark absolute inset-0" />
                    )}
                    {/* Weighted to the left, where the text sits, so the image stays legible on the right. */}
                    <div className="from-site-primary-deep/95 via-site-primary-dark/80 absolute inset-0 bg-gradient-to-r to-transparent" />

                    <div className="relative mx-auto flex min-h-[380px] max-w-[1200px] items-end px-6 py-14 sm:min-h-[460px]">
                        <div className="max-w-2xl">
                            <p className="text-site-accent font-display text-xs font-semibold tracking-[0.3em] uppercase">{t('home.journals')}</p>
                            <h1 className="font-display mt-3 text-4xl font-bold tracking-tight text-balance text-white sm:text-5xl">{site.title}</h1>
                            {site.tagline && <p className="text-site-accent font-body mt-4 text-lg">{site.tagline}</p>}
                            {cover?.caption && <p className="font-body mt-4 text-sm text-white/80">{cover.caption}</p>}
                        </div>
                    </div>
                </section>
            }
        >
            <div className="mx-auto max-w-[1200px] px-6 py-14">
                <div className="grid gap-10 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div>
                        <h2 className="text-site-primary-dark font-display mb-5 text-2xl font-semibold">{t('home.journals')}</h2>

                        {journals.length === 0 ? (
                            <div className="text-site-body border-site-border flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
                                <BookOpen className="text-site-border size-8" />
                                <p>{t('home.no_journals')}</p>
                            </div>
                        ) : (
                            <div className="grid gap-5 sm:grid-cols-2">
                                {journals.map((journal, index) => (
                                    <Link
                                        key={journal.path}
                                        href={`/journal/${journal.path}`}
                                        className="group border-site-border flex flex-col overflow-hidden rounded-xl border transition-shadow hover:shadow-md"
                                    >
                                        {/* No per-journal artwork exists, so a tinted band stands in — deterministic
                                            by index so a journal keeps the same colour between visits. */}
                                        <span
                                            className={
                                                'block h-24 ' +
                                                (index % 3 === 0
                                                    ? 'bg-site-primary'
                                                    : index % 3 === 1
                                                      ? 'bg-site-primary-dark'
                                                      : 'bg-site-surface-alt')
                                            }
                                        />
                                        <span className="flex flex-1 items-start justify-between gap-3 p-5">
                                            <span className="text-site-ink group-hover:text-site-primary font-display font-semibold transition-colors">
                                                {journal.title}
                                            </span>
                                            <ArrowUpRight className="text-site-primary mt-0.5 size-4 shrink-0 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}

                        <p className="mt-6">
                            <Link href="/journals" className="text-site-primary font-display text-sm font-semibold hover:underline">
                                {t('home.view_all_journals')}
                            </Link>
                        </p>
                    </div>

                    <aside className="space-y-6">
                        {site.about && (
                            <section className="bg-site-surface border-site-border rounded-xl border p-6">
                                <p className="text-site-body font-body leading-relaxed whitespace-pre-line">{site.about}</p>
                            </section>
                        )}

                        {highlights.map((highlight, index) => (
                            <section key={index} className="border-site-primary border-l-4 pl-4">
                                {highlight.title && <h3 className="text-site-primary-dark font-display font-semibold">{highlight.title}</h3>}
                                {highlight.body && <p className="text-site-body font-body mt-1.5 text-sm leading-relaxed">{highlight.body}</p>}
                            </section>
                        ))}
                    </aside>
                </div>
            </div>
        </PublicSiteLayout>
    );
}
