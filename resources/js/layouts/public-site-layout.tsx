import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Facebook, Globe, Instagram, Linkedin, LogIn, Menu, Search, Twitter, X, Youtube } from 'lucide-react';
import { type FormEventHandler, type ReactNode, useEffect, useState } from 'react';

export interface MenuItem {
    label: string;
    url: string;
}

export interface PublicSiteChrome {
    title: string;
    logoUrl: string | null;
    footerText?: string | null;
}

const SOCIAL_ICONS: Record<string, typeof Facebook> = {
    facebook: Facebook,
    twitter: Twitter,
    linkedin: Linkedin,
    youtube: Youtube,
    instagram: Instagram,
};

interface SiteBadge {
    label: string;
    url: string | null;
    logoUrl: string | null;
}

/**
 * The "Indexed by" and "Tools" rows.
 *
 * A badge with no logo renders as a text chip rather than a broken image — brand assets arrive
 * later than the decision to list a service, and a name alone is still true and still useful.
 */
function BadgeStrips({ badges, groups }: { badges: Record<string, SiteBadge[]>; groups: Record<string, string> }) {
    const present = Object.entries(groups).filter(([key]) => (badges?.[key]?.length ?? 0) > 0);

    if (present.length === 0) return null;

    return (
        <section className="border-site-border border-t bg-white py-12">
            <div className="mx-auto max-w-[1200px] space-y-8 px-6">
                {present.map(([key, heading]) => (
                    <div key={key}>
                        <h2 className="font-display text-site-body mb-4 text-center text-sm font-semibold tracking-wider uppercase">{heading}</h2>
                        <ul className="flex flex-wrap items-stretch justify-center gap-3">
                            {badges[key].map((badge, index) => {
                                const inner = badge.logoUrl ? (
                                    <img src={badge.logoUrl} alt={badge.label} className="max-h-10 max-w-[8rem] object-contain" />
                                ) : (
                                    <span className="font-display text-site-ink text-sm font-medium whitespace-nowrap">{badge.label}</span>
                                );

                                const className =
                                    'border-site-border flex h-20 min-w-[10rem] items-center justify-center rounded-lg border bg-white px-6 transition-colors';

                                return (
                                    <li key={`${badge.label}-${index}`}>
                                        {badge.url ? (
                                            <a
                                                href={badge.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className={`${className} hover:border-site-primary`}
                                            >
                                                {inner}
                                            </a>
                                        ) : (
                                            <span className={className}>{inner}</span>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </div>
        </section>
    );
}
/**
 * Search from the header, on every public page.
 *
 * Collapses to an icon below lg, where the full field would cost the masthead the room it needs
 * to stay legible — the icon opens the search page, which has the same field at full size.
 */
function HeaderSearch() {
    const { t } = useTranslations();
    const [term, setTerm] = useState('');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('search'), term.trim() === '' ? {} : { q: term.trim() });
    };

    return (
        <>
            <form onSubmit={submit} className="hidden 2xl:block">
                <label className="sr-only" htmlFor="header-search">
                    {t('search.title')}
                </label>
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-white/60" aria-hidden="true" />
                    <input
                        id="header-search"
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder={t('search.submit')}
                        className="font-display w-36 rounded-full border border-white/40 bg-white/10 py-2 pr-3 pl-9 text-sm text-white transition-[width,background-color] placeholder:text-white/60 focus:w-52 focus:bg-white/20 focus:outline-none xl:w-44 xl:focus:w-64"
                    />
                </div>
            </form>

            <Link
                href={route('search')}
                aria-label={t('search.title')}
                className="flex shrink-0 items-center justify-center rounded-full border border-white/40 p-2 text-white transition-colors hover:border-white hover:bg-white/10 2xl:hidden"
            >
                <Search className="size-4" />
            </Link>
        </>
    );
}
/**
 * EN / KH switch.
 *
 * A segmented pair rather than a dropdown: with two languages a select costs a click to discover
 * what the options even are, while both codes shown side by side make the choice and the current
 * state readable at a glance. Renders nothing when the site publishes in one language.
 *
 * POSTs because the choice is stored in the session — a GET link would let a crawler or a link
 * prefetch silently change the language for a real visitor.
 */
function LanguageSwitcher() {
    const { locale, locales } = useTranslations();
    const { url } = usePage();

    if (locales.length < 2) return null;

    const switchTo = (code: string) => {
        if (code === locale) return;

        // preserveScroll so the reader stays where they were reading; the page re-renders in the
        // other language around them rather than jumping to the top.
        router.post(route('locale.switch'), { locale: code }, { preserveScroll: true, preserveState: false });
    };

    return (
        <div className="flex items-center rounded-full border border-white/30 p-0.5" role="group" aria-label="Language">
            <Globe className="mx-1.5 size-3.5 shrink-0 text-white/70" aria-hidden="true" />
            {locales.map((option) => (
                <button
                    key={option.code}
                    type="button"
                    lang={option.code}
                    onClick={() => switchTo(option.code)}
                    aria-current={option.code === locale ? 'true' : undefined}
                    title={option.name}
                    className={
                        'font-display rounded-full px-2.5 py-1 text-xs font-semibold tracking-wider transition-colors ' +
                        (option.code === locale ? 'text-site-primary bg-white' : 'text-white/80 hover:text-white')
                    }
                >
                    {option.short}
                </button>
            ))}
        </div>
    );
}
/**
 * Shared "Academic Precision" chrome for every public site-level page (homepage, journals index,
 * CMS content pages). The header menu is admin-managed via the CMS section, so it has to render
 * identically everywhere — a visitor who follows a menu link must not land somewhere that has
 * lost the menu.
 */
export default function PublicSiteLayout({
    site,
    menuItems,
    socialLinks = {},
    title,
    hero,
    children,
}: {
    site: PublicSiteChrome;
    menuItems: MenuItem[];
    socialLinks?: Record<string, string>;
    /**
     * The page-specific part of the document title ONLY — "Ethics", not "Ethics — Alpha Journal".
     * app.tsx already appends the site name via Inertia's title callback, so including it here
     * produced tab titles reading "Ethics — Alpha Journal - Alpha Journal".
     *
     * Omit it entirely on the homepage, where the site name alone is the right title and there is
     * no page-specific part to prepend.
     */
    title?: string;
    hero?: ReactNode;
    children: ReactNode;
}) {
    const { t, locale } = useTranslations();
    const [menuOpen, setMenuOpen] = useState(false);

    // Shared by HandleInertiaRequests, so every page that uses this layout has them without
    // its controller needing to know they exist.
    const siteBadges = (usePage().props.siteBadges ?? {}) as Record<string, SiteBadge[]>;

    // Switching language is an Inertia visit, which never re-renders the blade root — so
    // <html lang> would keep whatever language the page was first loaded in. The div below
    // carries the right value for CSS, but screen readers and browser translation read the
    // document element.
    useEffect(() => {
        document.documentElement.lang = locale;
    }, [locale]);

    return (
        <>
            {/* Fonts are linked from the document head, which knows the active pairing. */}
            <Head title={title} />
            {/*
              lang drives the Khmer typography: the font stacks in app.css put Kantumruy Pro behind
              the Latin faces, and the [lang='km'] rules there loosen the leading its subscript
              consonants need (§7 — a Latin-only type scale is explicitly not shippable).
            */}
            <div lang={locale} className="font-body text-site-body flex min-h-screen flex-col bg-white antialiased">
                <nav className="bg-site-primary sticky top-0 z-50 w-full">
                    <div className="mx-auto flex h-20 w-full max-w-[1200px] items-center justify-between gap-4 px-6 xl:gap-12">
                        {/*
                          The masthead never truncates and never abuts the menu: the site's name is
                          the one thing a reader must be able to read, and when it sat flush against
                          the first nav link the two ran together as one line of white text.
                        */}
                        <Link href="/" className="flex min-w-0 shrink items-center gap-3 xl:shrink-0">
                            {site.logoUrl && <img src={site.logoUrl} alt={site.title} className="h-10 w-auto object-contain" />}
                            <span className="font-display truncate text-base font-bold text-white sm:text-lg xl:text-2xl">{site.title}</span>
                        </Link>
                        <div className="flex shrink-0 items-center gap-3 sm:gap-5">
                            {/*
                              Site navigation. Staff login is deliberately not one of these.

                              Inline only from xl, which is the width the whole row demonstrably
                              fits at; below that it moves into the panel behind the toggle rather
                              than being squeezed until either it or the masthead is clipped.
                            */}
                            <div className="hidden items-center gap-7 xl:flex">
                                {menuItems.map((item) => (
                                    <a
                                        key={item.url}
                                        href={item.url}
                                        className="font-display text-sm font-semibold tracking-wider whitespace-nowrap text-white/90 uppercase transition-colors hover:text-white"
                                    >
                                        {item.label}
                                    </a>
                                ))}
                            </div>

                            <HeaderSearch />

                            {/* Separates where you can go from what you can do. */}
                            {menuItems.length > 0 && <div className="hidden h-6 w-px shrink-0 bg-white/25 xl:block" aria-hidden="true" />}

                            <div className="hidden sm:block">
                                <LanguageSwitcher />
                            </div>

                            {/*
                              An account action, not a page — so it gets a button, not link styling.
                              Rendered identically to the menu it sat beside, readers took it for
                              another section of the site; the outlined pill says "this does
                              something different" without adding a word of copy.
                            */}
                            <Link
                                href="/login"
                                className="font-display flex shrink-0 items-center gap-2 rounded-full border border-white/40 px-3 py-2 text-sm font-semibold tracking-wider whitespace-nowrap text-white uppercase transition-colors hover:border-white hover:bg-white/10 sm:px-4"
                            >
                                <LogIn className="size-4" aria-hidden="true" />
                                {/* Icon-only on the narrowest phones — the label is what tipped the
                                    header into horizontal overflow, and the icon still reads. */}
                                <span className="hidden sm:inline">{t('nav.staff_login')}</span>
                            </Link>

                            {menuItems.length > 0 && (
                                <button
                                    type="button"
                                    onClick={() => setMenuOpen((open) => !open)}
                                    aria-expanded={menuOpen}
                                    aria-controls="site-menu"
                                    aria-label={t('nav.menu')}
                                    className="flex shrink-0 items-center justify-center rounded-full border border-white/40 p-2 text-white transition-colors hover:border-white hover:bg-white/10 xl:hidden"
                                >
                                    {menuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                                </button>
                            )}
                        </div>
                    </div>

                    {/*
                      The narrow-screen menu. Until this existed the links were simply hidden below
                      the inline breakpoint, so a phone reader had no way to reach Ethics, Contact
                      or the submission guidelines at all.
                    */}
                    {menuOpen && menuItems.length > 0 && (
                        <div id="site-menu" className="bg-site-primary-dark xl:hidden">
                            <div className="mx-auto flex max-w-[1200px] flex-col px-6 py-2">
                                {menuItems.map((item) => (
                                    <a
                                        key={item.url}
                                        href={item.url}
                                        className="font-display border-b border-white/10 py-3 text-sm font-semibold tracking-wider text-white/90 uppercase transition-colors hover:text-white"
                                    >
                                        {item.label}
                                    </a>
                                ))}

                                {/* Only here on the narrowest screens — above sm it lives in the bar. */}
                                <div className="flex py-3 sm:hidden">
                                    <LanguageSwitcher />
                                </div>
                            </div>
                        </div>
                    )}
                </nav>

                <main className="flex-grow">
                    {hero}
                    {children}
                </main>

                {/*
                  Deep blue rather than the old pale band: it bookends the page against the header
                  and stops the footer reading as an afterthought stuck to the bottom of the
                  content. Columns because the previous single row left most of the width empty.
                */}
                {/*
                  Indexing and tools, above the footer so every public page carries them — which is
                  also why they come from shared props rather than each of the six controllers that
                  render this layout.

                  Renders nothing at all when empty. An "Indexed by" heading over a blank row would
                  read as a broken page, and a placeholder there would be a claim nobody made.
                */}
                <BadgeStrips badges={siteBadges} groups={{ indexed_by: t('footer.indexed_by'), tools: t('footer.tools') }} />
                <footer className="bg-site-primary-dark mt-auto text-white">
                    <div className="mx-auto max-w-[1200px] px-6 py-14">
                        <div className="grid gap-10 md:grid-cols-3 lg:grid-cols-[2fr_1fr_1fr]">
                            <div>
                                <div className="flex items-center gap-3">
                                    {site.logoUrl && <img src={site.logoUrl} alt="" className="h-9 w-auto object-contain" />}
                                    <span className="font-display text-xl font-bold">{site.title}</span>
                                </div>
                                {site.footerText && (
                                    <p className="text-site-accent mt-4 max-w-sm text-sm leading-relaxed whitespace-pre-line">{site.footerText}</p>
                                )}

                                {Object.keys(socialLinks).length > 0 && (
                                    <div className="mt-6 flex items-center gap-3">
                                        {Object.entries(socialLinks).map(([platform, url]) => {
                                            const Icon = SOCIAL_ICONS[platform];

                                            return Icon ? (
                                                <a
                                                    key={platform}
                                                    href={url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    aria-label={platform}
                                                    className="flex size-9 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
                                                >
                                                    <Icon className="size-4" />
                                                </a>
                                            ) : null;
                                        })}
                                    </div>
                                )}
                            </div>

                            {/* The admin-managed header menu, repeated where readers look for it. */}
                            {menuItems.length > 0 && (
                                <nav aria-label="Footer navigation">
                                    <h2 className="font-display text-sm font-semibold tracking-wider text-white uppercase">{t('footer.explore')}</h2>
                                    <ul className="mt-4 space-y-2.5 text-sm">
                                        {menuItems.map((item) => (
                                            <li key={item.url}>
                                                <a href={item.url} className="text-site-accent transition-colors hover:text-white">
                                                    {item.label}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </nav>
                            )}

                            {/*
                              Only routes that genuinely exist. An "About us" or "Help" column
                              would look fuller and 404 on click.
                            */}
                            <nav aria-label="Participate">
                                <h2 className="font-display text-sm font-semibold tracking-wider text-white uppercase">{t('footer.participate')}</h2>
                                <ul className="mt-4 space-y-2.5 text-sm">
                                    <li>
                                        <Link href="/register" className="text-site-accent transition-colors hover:text-white">
                                            {t('footer.register_to_submit')}
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/login" className="text-site-accent transition-colors hover:text-white">
                                            {t('footer.staff_login')}
                                        </Link>
                                    </li>
                                    <li>
                                        {/*
                                          Indexers and aggregators harvest through this endpoint, so it
                                          stays on every public page — it is machine-facing plumbing, not
                                          a reader link, which is why it sits here rather than in Explore.
                                        */}
                                        <a href="/oai" className="text-site-accent transition-colors hover:text-white">
                                            {t('footer.oai')}
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>

                        <div className="text-site-accent mt-12 flex flex-col gap-2 border-t border-white/15 pt-6 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p>
                                © {new Date().getFullYear()} {site.title}
                            </p>
                            <p>{t('footer.strapline')}</p>
                        </div>

                        {/*
                          Platform attribution. Deliberately not site.title: that is the customer's
                          own name and is now admin-editable, so reading it from there would make a
                          renamed journal claim to be powered by itself.
                        */}
                        <p className="text-site-accent/70 mt-4 text-center text-xs">
                            {t('footer.powered_by')}{' '}
                            <a
                                href="https://www.alphalib.org"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="transition-colors hover:text-white"
                            >
                                Alpha Journal
                            </a>
                            <span className="mx-1.5">|</span>
                            <a
                                href="https://www.alphalib.org"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="transition-colors hover:text-white"
                            >
                                www.alphalib.org
                            </a>
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
