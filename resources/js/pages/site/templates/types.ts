import { type MenuItem } from '@/layouts/public-site-layout';

export interface JournalSummary {
    path: string;
    title: string;
}

export interface Highlight {
    title: string | null;
    body: string | null;
}

export interface HeroSlide {
    id: number;
    url: string;
    caption: string | null;
}

/**
 * What every landing-page template receives.
 *
 * Identical across all five on purpose: a template chooses how the site looks, never what it
 * says. Every one of these comes from the CMS the admin already uses, so switching design cannot
 * strand content or demand new fields be filled in.
 *
 * Each template must also survive any of it being absent — no hero slides, no highlights, no
 * journals — because a fresh install has all three.
 */
export interface TemplateProps {
    site: {
        title: string;
        tagline: string | null;
        about: string | null;
        footerText: string | null;
        logoUrl: string | null;
    };
    menuItems: MenuItem[];
    socialLinks: Record<string, string>;
    highlights: Highlight[];
    heroSlides: HeroSlide[];
    journals: JournalSummary[];
}
