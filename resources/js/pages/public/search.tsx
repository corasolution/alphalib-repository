import PublicSiteLayout, { type MenuItem, type PublicSiteChrome } from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, FileText, Search, SlidersHorizontal } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Result {
    id: number;
    title: string | null;
    abstract: string | null;
    doi: string | null;
    publishedAt: string | null;
    authors: string[];
    journal: { path: string; title: string } | null;
    issue: string | null;
    section: string | null;
    hasFile: boolean;
}

interface Paginated {
    data: Result[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    site: PublicSiteChrome;
    menuItems: MenuItem[];
    socialLinks: Record<string, string>;
    criteria: Record<string, string | number | null>;
    journals: { path: string; title: string }[];
    sections: { id: number; title: string }[];
    results: Paginated | null;
    searched: boolean;
}

const ADVANCED_FIELDS = ['title', 'author', 'abstract', 'doi', 'journal', 'section', 'year_from', 'year_to'];

/** Trimmed so a long abstract does not bury the next result. */
function snippet(text: string | null, length = 260): string | null {
    if (!text) return null;
    const clean = text.replace(/\s+/g, ' ').trim();

    return clean.length > length ? clean.slice(0, length).trimEnd() + '…' : clean;
}

export default function PublicSearch({ site, menuItems, socialLinks, criteria, journals, sections, results, searched }: Props) {
    const { t } = useTranslations();

    // Open when the reader already has advanced criteria in the URL — otherwise they would look
    // like an unfiltered search that mysteriously returns very little.
    const [advanced, setAdvanced] = useState(ADVANCED_FIELDS.some((f) => criteria[f]));
    const [form, setForm] = useState<Record<string, string>>(
        Object.fromEntries(Object.entries(criteria).map(([k, v]) => [k, v == null ? '' : String(v)])),
    );

    const set = (key: string, value: string) => setForm((f) => ({ ...f, [key]: value }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // Empty fields are dropped so the URL stays readable and shareable.
        const params = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
        router.get(route('search'), params, { preserveState: true, preserveScroll: false });
    };

    return (
        <PublicSiteLayout site={site} menuItems={menuItems} socialLinks={socialLinks} title={t('search.title')}>
            <Head title={t('search.title')} />

            <div className="mx-auto max-w-[1000px] px-6 py-12">
                <h1 className="font-display text-site-ink mb-6 text-3xl font-bold">{t('search.title')}</h1>

                <form onSubmit={submit} className="border-site-border bg-site-surface rounded-xl border p-5">
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <div className="relative flex-1">
                            <Search className="text-site-muted absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden="true" />
                            <input
                                type="search"
                                name="q"
                                value={form.q ?? ''}
                                onChange={(e) => set('q', e.target.value)}
                                placeholder={t('search.placeholder')}
                                aria-label={t('search.title')}
                                className="border-site-border focus:border-site-primary focus:ring-site-primary/20 w-full rounded-lg border bg-white py-3 pr-4 pl-10 text-base focus:ring-2 focus:outline-none"
                            />
                        </div>
                        <button
                            type="submit"
                            className="bg-site-primary font-display shrink-0 rounded-lg px-6 py-3 text-sm font-semibold tracking-wider text-white uppercase transition-opacity hover:opacity-90"
                        >
                            {t('search.submit')}
                        </button>
                    </div>

                    <button
                        type="button"
                        onClick={() => setAdvanced((open) => !open)}
                        aria-expanded={advanced}
                        className="text-site-body hover:text-site-primary mt-3 inline-flex items-center gap-1.5 text-sm underline-offset-4 hover:underline"
                    >
                        <SlidersHorizontal className="size-4" />
                        {advanced ? t('search.hide_advanced') : t('search.show_advanced')}
                    </button>

                    {advanced && (
                        <div className="border-site-border mt-4 grid gap-4 border-t pt-4 sm:grid-cols-2">
                            {[
                                { key: 'title', label: t('search.field_title') },
                                { key: 'author', label: t('search.field_author') },
                                { key: 'abstract', label: t('search.field_abstract') },
                                { key: 'doi', label: 'DOI' },
                            ].map((field) => (
                                <label key={field.key} className="grid gap-1.5 text-sm">
                                    <span className="text-site-body font-medium">{field.label}</span>
                                    <input
                                        value={form[field.key] ?? ''}
                                        onChange={(e) => set(field.key, e.target.value)}
                                        className="border-site-border focus:border-site-primary rounded-md border bg-white px-3 py-2 focus:outline-none"
                                    />
                                </label>
                            ))}

                            <label className="grid gap-1.5 text-sm">
                                <span className="text-site-body font-medium">{t('search.field_journal')}</span>
                                <select
                                    value={form.journal ?? ''}
                                    onChange={(e) => {
                                        set('journal', e.target.value);
                                        // Sections belong to one journal; keeping a stale one would
                                        // silently filter to a section the new journal has not got.
                                        set('section', '');
                                    }}
                                    className="border-site-border focus:border-site-primary rounded-md border bg-white px-3 py-2 focus:outline-none"
                                >
                                    <option value="">{t('search.any')}</option>
                                    {journals.map((j) => (
                                        <option key={j.path} value={j.path}>
                                            {j.title}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            {sections.length > 0 && (
                                <label className="grid gap-1.5 text-sm">
                                    <span className="text-site-body font-medium">{t('search.field_section')}</span>
                                    <select
                                        value={form.section ?? ''}
                                        onChange={(e) => set('section', e.target.value)}
                                        className="border-site-border focus:border-site-primary rounded-md border bg-white px-3 py-2 focus:outline-none"
                                    >
                                        <option value="">{t('search.any')}</option>
                                        {sections.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.title}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}

                            <div className="grid grid-cols-2 gap-3 text-sm">
                                {['year_from', 'year_to'].map((key) => (
                                    <label key={key} className="grid gap-1.5">
                                        <span className="text-site-body font-medium">
                                            {key === 'year_from' ? t('search.year_from') : t('search.year_to')}
                                        </span>
                                        <input
                                            type="number"
                                            inputMode="numeric"
                                            value={form[key] ?? ''}
                                            onChange={(e) => set(key, e.target.value)}
                                            placeholder={key === 'year_from' ? '2020' : '2026'}
                                            className="border-site-border focus:border-site-primary rounded-md border bg-white px-3 py-2 focus:outline-none"
                                        />
                                    </label>
                                ))}
                            </div>

                            <label className="grid gap-1.5 text-sm">
                                <span className="text-site-body font-medium">{t('search.sort')}</span>
                                <select
                                    value={form.sort ?? 'relevance'}
                                    onChange={(e) => set('sort', e.target.value)}
                                    className="border-site-border focus:border-site-primary rounded-md border bg-white px-3 py-2 focus:outline-none"
                                >
                                    <option value="relevance">{t('search.sort_relevance')}</option>
                                    <option value="newest">{t('search.sort_newest')}</option>
                                    <option value="oldest">{t('search.sort_oldest')}</option>
                                    <option value="title">{t('search.sort_title')}</option>
                                </select>
                            </label>
                        </div>
                    )}
                </form>

                {searched && results && (
                    <div className="mt-8">
                        <p className="text-site-body mb-4 text-sm">
                            {results.total === 0 ? t('search.no_results') : t('search.results_count', { count: results.total })}
                        </p>

                        <ol className="space-y-4">
                            {results.data.map((article) => (
                                <li key={article.id} className="border-site-border rounded-xl border bg-white p-5">
                                    <Link
                                        href={`/journal/${article.journal?.path}/article/${article.id}`}
                                        className="font-display text-site-ink hover:text-site-primary text-lg font-semibold"
                                    >
                                        {article.title ?? t('journal.untitled')}
                                    </Link>

                                    {article.authors.length > 0 && <p className="text-site-body mt-1 text-sm">{article.authors.join(', ')}</p>}

                                    {snippet(article.abstract) && (
                                        <p className="text-site-body mt-2 text-sm leading-relaxed">{snippet(article.abstract)}</p>
                                    )}

                                    <p className="text-site-muted mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                        {article.journal && <span>{article.journal.title}</span>}
                                        {article.issue && <span>· {article.issue}</span>}
                                        {article.section && <span>· {article.section}</span>}
                                        {article.publishedAt && (
                                            <span>
                                                · {new Date(article.publishedAt).toLocaleDateString('en-GB', { year: 'numeric', month: 'long' })}
                                            </span>
                                        )}
                                        {article.doi && <span className="font-mono">· {article.doi}</span>}
                                        {article.hasFile && (
                                            <span className="text-site-primary inline-flex items-center gap-1">
                                                <FileText className="size-3" />
                                                PDF
                                            </span>
                                        )}
                                    </p>
                                </li>
                            ))}
                        </ol>

                        {results.last_page > 1 && (
                            <nav aria-label="Pagination" className="mt-8 flex items-center justify-center gap-2">
                                {results.links.map((link, index) => {
                                    const isPrev = index === 0;
                                    const isNext = index === results.links.length - 1;
                                    const label = isPrev ? (
                                        <ChevronLeft className="size-4" />
                                    ) : isNext ? (
                                        <ChevronRight className="size-4" />
                                    ) : (
                                        link.label
                                    );

                                    if (!link.url) {
                                        return (
                                            <span key={index} className="text-site-muted px-3 py-1.5 text-sm">
                                                {label}
                                            </span>
                                        );
                                    }

                                    return (
                                        <Link
                                            key={index}
                                            href={link.url}
                                            className={
                                                'border-site-border rounded-md border px-3 py-1.5 text-sm transition-colors ' +
                                                (link.active
                                                    ? 'bg-site-primary border-site-primary text-white'
                                                    : 'hover:border-site-primary bg-white')
                                            }
                                        >
                                            {label}
                                        </Link>
                                    );
                                })}
                            </nav>
                        )}
                    </div>
                )}
            </div>
        </PublicSiteLayout>
    );
}
