import PublicSiteLayout, { type MenuItem } from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronRight, Download, FileText, Quote } from 'lucide-react';

interface Author {
    name: string;
    affiliation: string | null;
    orcid: string | null;
}

interface Galley {
    id: number;
    label: string;
    size: string | null;
}

interface Props {
    site: { title: string; logoUrl: string | null; footerText: string | null };
    menuItems: MenuItem[];
    socialLinks: Record<string, string>;
    journal: { path: string; title: string };
    article: {
        id: number;
        title: string | null;
        abstract: string | null;
        doi: string | null;
        version: number;
        publishedAt: string | null;
        section: string | null;
        issue: { identification: string; title: string | null } | null;
        authors: Author[];
        galleys: Galley[];
    };
}

const CITATION_FORMATS = [
    { format: 'bibtex', label: 'BibTeX' },
    { format: 'ris', label: 'RIS' },
    { format: 'csl', label: 'CSL-JSON' },
];

/**
 * The public article landing page (CLAUDE.md §7).
 *
 * Publishing used to produce a title in a list and nothing more — nothing to open, nothing to
 * download, nothing to cite. This is the page that makes a published article actually readable,
 * and the one Google Scholar crawls (its citation_* meta tags are rendered server-side in
 * resources/views/article.blade.php, since Scholar does not run our JavaScript).
 */
export default function PublicArticle({ site, menuItems, socialLinks, journal, article }: Props) {
    const { t, locale } = useTranslations();

    const published = article.publishedAt
        ? new Date(article.publishedAt).toLocaleDateString(locale === 'km' ? 'km-KH' : 'en-GB', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : null;

    // Whether the main column has anything at all to show.
    const hasBody = Boolean(article.abstract) || article.authors.some((a) => a.affiliation || a.orcid);

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            title={article.title ?? t('journal.untitled')}
            hero={
                <section className="bg-site-primary-dark relative overflow-hidden py-14 text-white">
                    <div className="from-site-primary-dark to-site-primary-deep absolute inset-0 bg-gradient-to-br opacity-90" />
                    <div className="relative mx-auto max-w-[1200px] px-6">
                        <nav aria-label="Breadcrumb" className="font-display text-site-accent mb-4 flex flex-wrap items-center gap-1.5 text-sm">
                            <Link href={`/journal/${journal.path}`} className="transition-colors hover:text-white">
                                {journal.title}
                            </Link>
                            {article.issue && (
                                <>
                                    <ChevronRight className="size-3.5 shrink-0" aria-hidden="true" />
                                    <span>{article.issue.identification}</span>
                                </>
                            )}
                        </nav>
                        <h1 className="font-display max-w-4xl text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                            {article.title ?? t('journal.untitled')}
                        </h1>

                        {article.authors.length > 0 && (
                            <p className="text-site-accent mt-4 max-w-4xl">{article.authors.map((author) => author.name).join(', ')}</p>
                        )}
                    </div>
                </section>
            }
        >
            <div
                className={cn(
                    'mx-auto grid max-w-[1200px] gap-10 px-6 py-14',
                    // Two columns only when the main one has something in it. An article with
                    // no abstract and no author details rendered ~500px of blank white beside
                    // the sidebar, which reads as a broken page rather than a short one.
                    hasBody ? 'lg:grid-cols-[minmax(0,1fr)_20rem]' : 'lg:max-w-[40rem]',
                )}
            >
                {hasBody && (
                    <article className="min-w-0">
                        {article.abstract && (
                            <>
                                <h2 className="font-display text-site-primary-dark mb-3 text-xl font-semibold">Abstract</h2>
                                <div className="text-site-body space-y-4 text-lg leading-[1.75]">
                                    {article.abstract
                                        .split(/\n\s*\n/)
                                        .filter((p) => p.trim() !== '')
                                        .map((paragraph, index) => (
                                            <p key={index} className="whitespace-pre-line">
                                                {paragraph.trim()}
                                            </p>
                                        ))}
                                </div>
                            </>
                        )}

                        {article.authors.some((a) => a.affiliation || a.orcid) && (
                            <section className="mt-10">
                                <h2 className="font-display text-site-primary-dark mb-3 text-xl font-semibold">Authors</h2>
                                <ul className="space-y-3">
                                    {article.authors.map((author, index) => (
                                        <li key={index}>
                                            <p className="font-display text-site-ink font-semibold">{author.name}</p>
                                            {author.affiliation && <p className="text-site-body text-sm">{author.affiliation}</p>}
                                            {author.orcid && (
                                                <a
                                                    href={`https://orcid.org/${author.orcid}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-site-primary text-sm hover:underline"
                                                >
                                                    ORCID {author.orcid}
                                                </a>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </article>
                )}

                <aside className="space-y-6 lg:sticky lg:top-24 lg:self-start">
                    {/*
                      Downloads first: a reader who came here from a search result wants the PDF,
                      not the metadata. An article with no galley says so plainly rather than
                      showing an empty box that looks broken.
                    */}
                    <section className="border-site-border bg-site-surface rounded-xl border p-5">
                        <h2 className="font-display text-site-primary-dark mb-3 text-sm font-semibold tracking-wider uppercase">Download</h2>
                        {article.galleys.length === 0 ? (
                            <p className="text-site-body flex items-start gap-2 text-sm">
                                <FileText className="text-site-muted mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                No file has been published for this article yet.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {article.galleys.map((galley) => (
                                    <li key={galley.id}>
                                        <a
                                            href={`/journal/${journal.path}/article/${article.id}/galley/${galley.id}`}
                                            className="bg-site-primary font-display hover:bg-site-primary-dark flex items-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold text-white transition-colors"
                                        >
                                            <Download className="size-4" aria-hidden="true" />
                                            {galley.label}
                                            {galley.size && <span className="ml-auto text-xs font-normal text-white/80">{galley.size}</span>}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="border-site-border rounded-xl border p-5">
                        <h2 className="font-display text-site-primary-dark mb-3 text-sm font-semibold tracking-wider uppercase">
                            About this article
                        </h2>
                        <dl className="space-y-2 text-sm">
                            {article.issue && (
                                <div>
                                    <dt className="text-site-muted">Issue</dt>
                                    <dd className="text-site-ink">{article.issue.identification}</dd>
                                </div>
                            )}
                            {article.section && (
                                <div>
                                    <dt className="text-site-muted">Section</dt>
                                    <dd className="text-site-ink">{article.section}</dd>
                                </div>
                            )}
                            {published && (
                                <div>
                                    <dt className="text-site-muted">Published</dt>
                                    <dd className="text-site-ink">{published}</dd>
                                </div>
                            )}
                            {article.doi && (
                                <div>
                                    <dt className="text-site-muted">DOI</dt>
                                    <dd>
                                        <a
                                            href={`https://doi.org/${article.doi}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-site-primary break-all hover:underline"
                                        >
                                            {article.doi}
                                        </a>
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </section>

                    <section className="border-site-border rounded-xl border p-5">
                        <h2 className="font-display text-site-primary-dark mb-3 flex items-center gap-2 text-sm font-semibold tracking-wider uppercase">
                            <Quote className="size-4" aria-hidden="true" />
                            Cite this
                        </h2>
                        <ul className="flex flex-wrap gap-2">
                            {CITATION_FORMATS.map(({ format, label }) => (
                                <li key={format}>
                                    <a
                                        href={`/journal/${journal.path}/article/${article.id}/cite/${format}`}
                                        className="border-site-border font-display text-site-body hover:border-site-primary hover:text-site-primary inline-block rounded border px-3 py-1.5 text-sm transition-colors"
                                    >
                                        {label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </section>
                </aside>
            </div>
        </PublicSiteLayout>
    );
}
