import PublicSiteLayout, { type MenuItem } from '@/layouts/public-site-layout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ChevronRight, FileText } from 'lucide-react';

interface BoardMember {
    id: number;
    name: string;
    role: string | null;
    affiliation: string | null;
    photoUrl: string | null;
    initials: string;
}

interface BoardGroup {
    id: number;
    name: string;
    members: BoardMember[];
}

interface Props {
    site: {
        title: string;
        logoUrl: string | null;
        footerText: string | null;
    };
    menuItems: MenuItem[];
    socialLinks: Record<string, string>;
    page: {
        title: string;
        body: string | null;
        layout: string | null;
        updatedAt: string | null;
    };
    boardGroups: BoardGroup[];
}

/**
 * Opt-in heading marker for page bodies. The CMS body is a plain textarea, so long documents
 * (an ethics statement, submission guidelines) would otherwise render as an undifferentiated
 * run of paragraphs with no way to see the sections. An explicit prefix beats guessing from
 * line length or punctuation, which misfires on short paragraphs and on headings that end in
 * a colon.
 */
const HEADING_PREFIX = '## ';

/**
 * Splits the stored plain-text body into real paragraphs on blank lines.
 *
 * The body is a plain textarea in the CMS, so it arrives as one string. Rendering it as a single
 * <p> with `whitespace-pre-line` does preserve the line breaks, but every paragraph then sits one
 * line-height apart with no more separation than a wrapped line — which is exactly why a policy
 * page like Ethics read as an undifferentiated block. Paragraphs need space between them to read
 * as paragraphs.
 */
function paragraphsOf(body: string): string[] {
    return body
        .split(/\n\s*\n/)
        .map((paragraph) => paragraph.trim())
        .filter((paragraph) => paragraph.length > 0);
}

/**
 * One person on the editorial board.
 *
 * The photo is optional by design: a board of eighteen rarely has eighteen portraits ready, and a
 * page that looks broken until every one is collected never gets finished. Missing photos fall
 * back to an initials disc in the site blue, so the grid reads as deliberate either way.
 */
function BoardMemberCard({ member }: { member: BoardMember }) {
    return (
        <li className="flex flex-col items-center rounded-xl border border-site-border bg-white p-6 text-center shadow-sm transition-shadow hover:shadow-md">
            {member.photoUrl ? (
                <img src={member.photoUrl} alt="" className="size-24 rounded-full object-cover ring-2 ring-site-surface" />
            ) : (
                <span
                    aria-hidden="true"
                    className="flex size-24 items-center justify-center rounded-full bg-site-primary-dark font-display text-2xl font-bold text-white"
                >
                    {member.initials}
                </span>
            )}

            <p className="mt-4 font-display text-base font-semibold text-site-ink">{member.name}</p>
            {member.role && <p className="mt-1 font-display text-sm text-site-primary">{member.role}</p>}
            {member.affiliation && <p className="mt-1 text-sm text-site-muted">{member.affiliation}</p>}
        </li>
    );
}

function BoardGroups({ groups }: { groups: BoardGroup[] }) {
    return (
        <div className="mt-12 space-y-12">
            {groups.map((group) => (
                <section key={group.id}>
                    <h2 className="mb-6 font-display text-2xl font-semibold text-site-primary-dark">{group.name}</h2>
                    <ul className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {group.members.map((member) => (
                            <BoardMemberCard key={member.id} member={member} />
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
export default function SitePageShow({ site, menuItems, socialLinks, page, boardGroups }: Props) {
    const { t, locale } = useTranslations();
    const paragraphs = page.body ? paragraphsOf(page.body) : [];

    return (
        <PublicSiteLayout
            site={site}
            menuItems={menuItems}
            socialLinks={socialLinks}
            title={page.title}
            hero={
                /*
                  The same title band the Journals index uses. A CMS page previously opened with a
                  bare heading floating in white space, which read as an unstyled document rather
                  than a page of this site — the band is what visually anchors it to the rest.
                */
                <section className="relative overflow-hidden bg-site-primary-dark py-14 text-white">
                    <div className="absolute inset-0 bg-gradient-to-br from-site-primary-dark to-site-primary-deep opacity-90" />
                    <div className="relative mx-auto max-w-[1200px] px-6">
                        <nav aria-label="Breadcrumb" className="mb-4 flex items-center gap-1.5 font-display text-sm text-site-accent">
                            <Link href="/" className="transition-colors hover:text-white">
                                {t('page.home')}
                            </Link>
                            <ChevronRight className="size-3.5 shrink-0" aria-hidden="true" />
                            <span className="text-white/80">{page.title}</span>
                        </nav>
                        <h1 className="max-w-3xl font-display text-[22px] font-bold tracking-tight text-balance">{page.title}</h1>
                    </div>
                </section>
            }
        >
            {/*
              Two nested constraints, doing different jobs. The outer one is the site's 1200px
              grid, so the body starts on the same left edge as the breadcrumb and heading above
              it — centring the prose instead left the title adrift from its own content. The
              inner one caps the measure: at 18px serif a full-width line runs past 140
              characters, and the eye loses the start of the next line.
            */}
            <article className="mx-auto w-full max-w-[1200px] px-6 py-14">
                <div className="max-w-[46rem]">
                    {paragraphs.length > 0 ? (
                        <div className="space-y-6 text-lg leading-[1.75] text-site-body">
                            {paragraphs.map((paragraph, index) =>
                                // Index keys are safe here: this list is derived from immutable page
                                // content, never reordered, filtered, or edited in place.
                                paragraph.startsWith(HEADING_PREFIX) ? (
                                    <h2 key={index} className="pt-4 font-display text-2xl font-semibold text-site-primary-dark first:pt-0">
                                        {paragraph.slice(HEADING_PREFIX.length).trim()}
                                    </h2>
                                ) : (
                                    <p key={index} className="whitespace-pre-line">
                                        {paragraph}
                                    </p>
                                ),
                            )}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-site-border py-16 text-center">
                            <FileText className="size-8 text-site-border" aria-hidden="true" />
                            <p className="font-display text-site-muted">{t('page.empty')}</p>
                        </div>
                    )}
                </div>

                {/*
                  Outside the 46rem prose measure on purpose: that cap exists to keep line length
                  readable, and applying it to a card grid would squeeze three columns into one.
                */}
                {boardGroups.length > 0 && <BoardGroups groups={boardGroups} />}

                {/*
                  Worth showing on this kind of page specifically: for an ethics or policy
                  statement, when it was last revised is part of the content's credibility, not
                  decoration. Last, below the board, so it dates the whole page rather than
                  appearing to belong to the intro paragraph.
                */}
                {page.updatedAt && (paragraphs.length > 0 || boardGroups.length > 0) && (
                    <p className="mt-12 border-t border-site-border pt-6 font-display text-sm text-site-muted">
                        {t('page.last_updated', {
                            date: new Date(page.updatedAt).toLocaleDateString(locale === 'km' ? 'km-KH' : 'en-GB', {
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            }),
                        })}
                    </p>
                )}
            </article>
        </PublicSiteLayout>
    );
}
