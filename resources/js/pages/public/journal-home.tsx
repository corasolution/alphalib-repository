import JournalIssues, { type ArchiveIssue, type CurrentIssue } from '@/components/journal-issues';
import PublicSiteLayout, { type MenuItem } from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';

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
    currentIssue: CurrentIssue | null;
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

                <JournalIssues journalPath={journal.path} currentIssue={currentIssue} archive={archive} />

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
