import PublicSiteLayout from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, BookOpen, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import { type HeroSlide, type Highlight, type TemplateProps } from './types';

const SLIDE_INTERVAL_MS = 6000;

function HeroSlideshow({ slides, title, tagline }: { slides: HeroSlide[]; title: string; tagline: string | null }) {
    const [active, setActive] = useState(0);

    useEffect(() => {
        if (slides.length < 2) return;

        const timer = setInterval(() => setActive((i) => (i + 1) % slides.length), SLIDE_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [slides.length]);

    const current = slides[active];

    return (
        <section className="relative h-[420px] overflow-hidden text-white sm:h-[480px]">
            {slides.map((slide, index) => (
                <div
                    key={slide.id}
                    className="absolute inset-0 bg-cover bg-center transition-opacity duration-1000"
                    style={{ backgroundImage: `url(${slide.url})`, opacity: index === active ? 1 : 0 }}
                />
            ))}
            <div className="from-site-primary-dark/90 via-site-primary-dark/50 to-site-primary-dark/30 absolute inset-0 bg-gradient-to-t" />

            <div className="relative flex h-full flex-col items-center justify-center px-6 text-center">
                <h1 className="font-display text-4xl font-bold tracking-tight drop-shadow sm:text-5xl">{title}</h1>
                {tagline && <p className="font-display text-site-accent mt-4 text-xl italic drop-shadow">{tagline}</p>}
                {current?.caption && <p className="font-body mt-4 max-w-2xl text-lg drop-shadow">{current.caption}</p>}
            </div>

            {slides.length > 1 && (
                <>
                    <button
                        type="button"
                        onClick={() => setActive((i) => (i - 1 + slides.length) % slides.length)}
                        aria-label="Previous slide"
                        className="absolute top-1/2 left-4 -translate-y-1/2 rounded-full bg-black/30 p-2 transition-colors hover:bg-black/50"
                    >
                        <ChevronLeft className="size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setActive((i) => (i + 1) % slides.length)}
                        aria-label="Next slide"
                        className="absolute top-1/2 right-4 -translate-y-1/2 rounded-full bg-black/30 p-2 transition-colors hover:bg-black/50"
                    >
                        <ChevronRight className="size-5" />
                    </button>
                    <div className="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2">
                        {slides.map((slide, index) => (
                            <button
                                key={slide.id}
                                type="button"
                                aria-label={`Go to slide ${index + 1}`}
                                onClick={() => setActive(index)}
                                className={`size-2 rounded-full transition-colors ${index === active ? 'bg-white' : 'bg-white/40'}`}
                            />
                        ))}
                    </div>
                </>
            )}
        </section>
    );
}

/**
 * The numbered highlight strip: three dark cards on a soft blue band, each with a ring-bordered
 * number straddling its left edge.
 *
 * The number circle is positioned rather than laid out inline because it deliberately overhangs
 * the card — hence the left padding on the wrapper, which reserves the space it hangs into so it
 * never collides with a neighbouring column.
 */
function HighlightStrip({ highlights }: { highlights: Highlight[] }) {
    return (
        <section className="from-site-surface-alt via-site-surface to-site-surface-alt bg-gradient-to-r py-12">
            <div className="mx-auto grid max-w-[1200px] grid-cols-1 gap-y-10 px-6 md:grid-cols-3 md:gap-x-10">
                {highlights.map((highlight, index) => (
                    <div key={index} className="relative pl-8">
                        <div className="from-site-primary-dark to-site-primary h-full rounded-lg bg-gradient-to-br py-7 pr-7 pl-12 text-center text-white shadow-md">
                            {highlight.title && <h3 className="text-lg font-bold italic">{highlight.title}</h3>}
                            {highlight.body && <p className="mt-2 text-[13px] leading-relaxed text-white/90 italic">{highlight.body}</p>}
                        </div>
                        <span
                            aria-hidden="true"
                            className="bg-site-primary-dark font-display absolute top-1/2 left-0 flex size-14 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white text-2xl font-bold text-white shadow"
                        >
                            {index + 1}
                        </span>
                    </div>
                ))}
            </div>
        </section>
    );
}

/**
 * Same "Academic Precision" design language as the per-journal public page — real content only.
 * If Site.redirect_journal_id is set this route never renders; '/' goes straight to that one
 * journal instead (SiteController::home). Logo, header menu, footer text, and social links are
 * all optional and admin-managed via the CMS section — a block simply doesn't render when unset.
 */
export default function AcademicTemplate({ site, menuItems, socialLinks, highlights, heroSlides, journals }: TemplateProps) {
    const { t } = useTranslations();

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            // No title prop: the homepage's title is the site name, which app.tsx appends anyway.
            hero={
                heroSlides.length > 0 ? (
                    <HeroSlideshow slides={heroSlides} title={site.title} tagline={site.tagline} />
                ) : (
                    <section className="bg-site-primary-dark relative overflow-hidden py-24 text-white">
                        <div className="from-site-primary-dark to-site-primary-deep absolute inset-0 bg-gradient-to-br opacity-90" />
                        <div className="relative mx-auto max-w-[1200px] space-y-6 px-6 text-center">
                            <h1 className="font-display text-4xl font-bold tracking-tight sm:text-5xl">{site.title}</h1>
                            {site.tagline && <p className="font-display text-site-accent text-xl italic">{site.tagline}</p>}
                        </div>
                    </section>
                )
            }
        >
            {/*
              The highlight strip replaces the plain about box. About is kept as the fallback so a
              site that has not filled the cards in yet still shows its intro rather than nothing.
            */}
            {highlights.length > 0 ? (
                <HighlightStrip highlights={highlights} />
            ) : (
                site.about && (
                    <div className="mx-auto max-w-[1200px] px-6 pt-16">
                        <section className="border-site-border bg-site-surface rounded border p-8">
                            <p className="text-lg leading-relaxed whitespace-pre-line">{site.about}</p>
                        </section>
                    </div>
                )
            )}

            <div className="mx-auto max-w-[1200px] space-y-10 px-6 py-16">
                <section>
                    <div className="mb-6 flex items-center justify-between">
                        <h2 className="font-display text-site-primary-dark text-2xl font-semibold">{t('home.journals')}</h2>
                        {journals.length > 0 && (
                            <Link href="/journals" className="font-display text-site-primary text-sm font-semibold hover:underline">
                                {t('home.view_all_journals')}
                            </Link>
                        )}
                    </div>
                    {journals.length === 0 ? (
                        <div className="text-site-body flex flex-col items-center gap-2 py-12 text-center">
                            <BookOpen className="text-site-border size-8" />
                            <p>{t('home.no_journals')}</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            {journals.map((journal) => (
                                <Link
                                    key={journal.path}
                                    href={`/journal/${journal.path}`}
                                    className="group border-site-border flex items-center justify-between rounded-xl border bg-white p-6 shadow-sm transition-shadow hover:shadow-md"
                                >
                                    <span className="font-display text-site-ink text-lg font-semibold">{journal.title}</span>
                                    <ArrowUpRight className="text-site-primary size-5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                </Link>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </PublicSiteLayout>
    );
}
