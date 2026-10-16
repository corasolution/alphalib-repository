import FeaturedJournal from '@/components/featured-journal';
import PublicSiteLayout from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, BookOpen, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import { type HeroSlide, type TemplateProps } from './types';

const SLIDE_INTERVAL_MS = 6000;

/** Fine vertical hairlines over the navy, the same texture the catalogue header uses. */
const HAIRLINES = 'repeating-linear-gradient(90deg, rgba(255,255,255,.04) 0 1px, transparent 1px 3px)';
/** The warm glow in the top-right corner of the hero. */
const GOLD_GLOW = 'radial-gradient(900px 340px at 85% -10%, rgba(247,148,29,.22), transparent 70%)';

function HeroCopy({ title, tagline, caption }: { title: string; tagline: string | null; caption?: string | null }) {
    const { t } = useTranslations();

    return (
        <div className="relative mx-auto flex max-w-[1200px] flex-col items-start px-6 pt-8 pb-16 sm:pt-10 sm:pb-20">
            <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-xs font-semibold tracking-widest text-white/90 uppercase backdrop-blur">
                <span className="size-1.5 rounded-full bg-amber-400" />
                {t('home.journals')}
            </span>
            <h1 className="font-display max-w-4xl text-[33px] leading-[1.3] font-extrabold tracking-tight text-balance text-white drop-shadow-sm">
                {title}
            </h1>
            {tagline && <p className="text-site-accent font-display mt-5 max-w-2xl text-lg leading-relaxed sm:text-xl">{tagline}</p>}
            {caption && <p className="font-body mt-3 max-w-2xl text-base text-white/80">{caption}</p>}

            <div className="mt-9 flex flex-wrap items-center gap-3">
                <Link
                    href="/journals"
                    className="font-display text-site-primary-deep inline-flex items-center gap-2 rounded-full bg-gradient-to-b from-amber-300 to-amber-400 px-6 py-3 text-sm font-bold shadow-lg shadow-amber-900/30 transition-all hover:-translate-y-0.5 hover:shadow-xl"
                >
                    {t('home.view_all_journals')}
                    <ArrowRight className="size-4" />
                </Link>
            </div>
        </div>
    );
}

function Hero({ slides, title, tagline }: { slides: HeroSlide[]; title: string; tagline: string | null }) {
    const [active, setActive] = useState(0);

    useEffect(() => {
        if (slides.length < 2) return;

        const timer = setInterval(() => setActive((i) => (i + 1) % slides.length), SLIDE_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [slides.length]);

    const current = slides[active];

    return (
        <section className="bg-site-primary-deep relative overflow-hidden">
            {slides.map((slide, index) => (
                <div
                    key={slide.id}
                    className="absolute inset-0 bg-cover bg-center transition-opacity duration-1000"
                    style={{ backgroundImage: `url(${slide.url})`, opacity: index === active ? 1 : 0 }}
                />
            ))}

            {/* The navy wash keeps the type readable over any photo, and is the whole background when there are none. */}
            <div
                className="from-site-primary-deep via-site-primary-dark/95 to-site-primary/80 absolute inset-0 bg-gradient-to-br"
                style={{ opacity: slides.length > 0 ? 0.92 : 1 }}
            />
            <div className="absolute inset-0" style={{ backgroundImage: `${GOLD_GLOW}, ${HAIRLINES}` }} />

            <HeroCopy title={title} tagline={tagline} caption={current?.caption} />

            {slides.length > 1 && (
                <div className="absolute right-6 bottom-6 flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setActive((i) => (i - 1 + slides.length) % slides.length)}
                        aria-label="Previous slide"
                        className="rounded-full border border-white/20 bg-white/10 p-2 text-white backdrop-blur transition-colors hover:bg-white/25"
                    >
                        <ChevronLeft className="size-4" />
                    </button>
                    <div className="flex gap-1.5 px-1">
                        {slides.map((slide, index) => (
                            <button
                                key={slide.id}
                                type="button"
                                aria-label={`Go to slide ${index + 1}`}
                                onClick={() => setActive(index)}
                                className={`h-1.5 rounded-full transition-all ${index === active ? 'w-6 bg-amber-400' : 'w-1.5 bg-white/40'}`}
                            />
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={() => setActive((i) => (i + 1) % slides.length)}
                        aria-label="Next slide"
                        className="rounded-full border border-white/20 bg-white/10 p-2 text-white backdrop-blur transition-colors hover:bg-white/25"
                    >
                        <ChevronRight className="size-4" />
                    </button>
                </div>
            )}
        </section>
    );
}

/**
 * Blue — a deep navy hero with a warm gold glow, elevated white cards, and soft blue surfaces.
 * Takes its palette and finish from the Rule Library catalogue (navy ramp, gold accent, pill
 * buttons, hairline texture, soft shadows).
 *
 * Every block degrades quietly: no slides gives the plain navy hero, no highlights falls back to
 * the about text, no journals shows the empty state.
 */
export default function BlueTemplate({ site, menuItems, socialLinks, highlights, heroSlides, journals, featuredJournal }: TemplateProps) {
    const { t } = useTranslations();

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            hero={<Hero slides={heroSlides} title={site.title} tagline={site.tagline} />}
        >
            <div className="bg-site-surface">
                {highlights.length > 0 ? (
                    <section className="relative z-10 mx-auto -mt-10 grid max-w-[1200px] gap-5 px-6 md:grid-cols-3">
                        {highlights.map((highlight, index) => (
                            <div
                                key={index}
                                className="border-site-border group relative overflow-hidden rounded-2xl border border-t-4 border-t-amber-400 bg-white p-7 shadow-[0_10px_30px_-12px_rgba(6,42,82,.25)] transition-all hover:-translate-y-1 hover:shadow-[0_18px_40px_-14px_rgba(6,42,82,.35)]"
                            >
                                <span className="font-display text-site-surface-alt absolute -top-2 right-4 text-7xl font-extrabold tabular-nums select-none">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <div className="relative">
                                    {highlight.title && <h3 className="font-display text-site-primary-dark text-lg font-bold">{highlight.title}</h3>}
                                    {highlight.body && <p className="text-site-body mt-2 text-sm leading-relaxed">{highlight.body}</p>}
                                </div>
                            </div>
                        ))}
                    </section>
                ) : (
                    site.about && (
                        <section className="relative z-10 mx-auto -mt-10 max-w-[1200px] px-6">
                            <div className="border-site-border rounded-2xl border border-l-4 border-l-amber-400 bg-white p-8 shadow-[0_10px_30px_-12px_rgba(6,42,82,.25)]">
                                <p className="text-site-body text-lg leading-relaxed whitespace-pre-line">{site.about}</p>
                            </div>
                        </section>
                    )
                )}

                {featuredJournal ? (
                    <div className="mx-auto max-w-[1200px] px-6 py-16">
                        <FeaturedJournal featured={featuredJournal} />
                    </div>
                ) : (
                    <section className="mx-auto max-w-[1200px] px-6 py-16">
                        <div className="mb-8 flex items-end justify-between gap-4">
                            <div>
                                <div className="h-1 w-10 rounded-full bg-amber-400" />
                                <h2 className="font-display text-site-primary-deep mt-3 text-3xl font-extrabold tracking-tight">
                                    {t('home.journals')}
                                </h2>
                            </div>
                            {journals.length > 0 && (
                                <Link
                                    href="/journals"
                                    className="font-display text-site-primary hover:bg-site-primary inline-flex items-center gap-1.5 rounded-full border border-current px-4 py-2 text-sm font-semibold transition-colors hover:text-white"
                                >
                                    {t('home.view_all_journals')}
                                    <ArrowRight className="size-4" />
                                </Link>
                            )}
                        </div>

                        {journals.length === 0 ? (
                            <div className="border-site-border text-site-body flex flex-col items-center gap-2 rounded-2xl border border-dashed bg-white py-16 text-center">
                                <BookOpen className="text-site-primary/40 size-8" />
                                <p>{t('home.no_journals')}</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                {journals.map((journal) => (
                                    <Link
                                        key={journal.path}
                                        href={`/journal/${journal.path}`}
                                        className="group border-site-border hover:border-site-primary/40 flex items-center gap-4 rounded-2xl border bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-[0_14px_32px_-14px_rgba(6,42,82,.35)]"
                                    >
                                        <span className="from-site-primary-dark to-site-primary flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-inner">
                                            <BookOpen className="size-5" />
                                        </span>
                                        <span className="font-display text-site-ink min-w-0 flex-1 text-lg leading-snug font-semibold">
                                            {journal.title}
                                        </span>
                                        <span className="bg-site-surface-alt text-site-primary group-hover:bg-site-primary flex size-9 shrink-0 items-center justify-center rounded-full transition-colors group-hover:text-white">
                                            <ArrowUpRight className="size-4" />
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                )}
            </div>
        </PublicSiteLayout>
    );
}
