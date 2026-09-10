import PublicSiteLayout, { type MenuItem } from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, BookOpen } from 'lucide-react';

interface JournalSummary {
    path: string;
    title: string;
    description: string | null;
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
    journals: JournalSummary[];
}

export default function SiteJournals({ site, menuItems, socialLinks, journals }: Props) {
    const { t } = useTranslations();

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            title="Journals"
            hero={
                <section className="relative overflow-hidden bg-site-primary-dark py-16 text-white">
                    <div className="absolute inset-0 bg-gradient-to-br from-site-primary-dark to-site-primary-deep opacity-90" />
                    <div className="relative mx-auto max-w-[1200px] px-6 text-center">
                        <h1 className="font-display text-4xl font-bold tracking-tight">{t('journals.title')}</h1>
                        <p className="mt-3 font-display text-lg text-site-accent">{t('journals.subtitle', { site: site.title })}</p>
                    </div>
                </section>
            }
        >
            <div className="mx-auto max-w-[1200px] px-6 py-16">
                {journals.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-16 text-center text-site-body">
                        <BookOpen className="size-8 text-site-border" />
                        <p>{t('home.no_journals')}</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        {journals.map((journal) => (
                            <Link
                                key={journal.path}
                                href={`/journal/${journal.path}`}
                                className="group flex flex-col rounded-xl border border-site-border bg-white p-6 shadow-sm transition-shadow hover:shadow-md"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <span className="font-display text-lg font-semibold text-site-ink">{journal.title}</span>
                                    <ArrowUpRight className="mt-1 size-5 shrink-0 text-site-primary transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                </div>
                                {journal.description && <p className="mt-3 leading-relaxed text-site-body">{journal.description}</p>}
                                <p className="mt-4 font-display text-sm text-site-muted">
                                    {t('journals.articles', { count: journal.articleCount })}
                                </p>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </PublicSiteLayout>
    );
}
