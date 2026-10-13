import PublicSiteLayout, { type MenuItem, type PublicSiteChrome } from '@/layouts/public-site-layout';
import { Head, Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface Props {
    site: PublicSiteChrome;
    menuItems: MenuItem[];
    socialLinks: Record<string, string>;
}

/**
 * The platform handbook.
 *
 * Written as a page rather than a CMS entry because the parts people actually return for — the
 * workflow diagram and the eight reference tables — cannot survive a plain-text body. It is
 * light-only and built from the site palette tokens, like every other public page, so an admin
 * changing the journal's colours restyles this too.
 */

const CONTENTS = [
    ['1', 'The platform', 's1'],
    ['2', 'Roles', 's2'],
    ['3', 'Your account', 's3'],
    ['4', 'Submitting', 's4'],
    ['5', 'Reviewing', 's5'],
    ['6', 'The editorial workflow', 's6'],
    ['7', 'Production & publishing', 's7'],
    ['8', 'Issues', 's8'],
    ['9', 'People', 's9'],
    ['10', 'Journal settings', 's10'],
    ['11', 'Site administration', 's11'],
    ['12', 'The public site', 's12'],
    ['13', 'Reference', 's13'],
    ['14', 'When things stop', 's14'],
] as const;

function Section({ num, title, id, children }: { num: string; title: string; id: string; children: ReactNode }) {
    return (
        <section id={id} className="scroll-mt-24 pt-14 first:pt-0">
            <div className="border-site-ink mb-6 flex items-baseline gap-3 border-b-2 pb-3">
                <span className="text-site-primary font-mono text-sm font-medium">§{num}</span>
                <h2 className="font-display text-site-ink text-2xl font-semibold tracking-tight sm:text-[28px]">{title}</h2>
            </div>
            {children}
        </section>
    );
}

function H3({ children }: { children: ReactNode }) {
    return <h3 className="font-display text-site-ink mt-8 mb-2.5 text-xl font-semibold">{children}</h3>;
}

/** Where a screen lives, in the site's own navigation words. */
function Where({ children }: { children: ReactNode }) {
    return (
        <p className="border-site-primary bg-site-surface text-site-muted my-4 max-w-[68ch] overflow-x-auto border-l-[3px] px-4 py-2.5 font-mono text-xs">
            <span className="text-site-ink mr-2 text-[10px] font-medium tracking-wider uppercase">Where</span>
            {children}
        </p>
    );
}

function Note({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="border-site-border bg-site-surface mt-5 max-w-[68ch] rounded border p-4">
            <span className="text-site-muted mb-1.5 block font-mono text-[10px] tracking-[0.12em] uppercase">{label}</span>
            <div className="text-site-body space-y-2 text-[15px]">{children}</div>
        </div>
    );
}

/** A rule the platform enforces — visually distinct from a note, because ignoring it stops you. */
function Rule({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="mt-5 max-w-[68ch] rounded border border-amber-300 bg-amber-50 p-4">
            <span className="mb-1.5 block font-mono text-[10px] tracking-[0.12em] text-amber-800 uppercase">{label}</span>
            <div className="space-y-2 text-[15px] text-amber-950">{children}</div>
        </div>
    );
}

function Steps({ items }: { items: ReactNode[] }) {
    return (
        <ol className="mt-4 flex max-w-[68ch] list-none flex-col gap-4 p-0">
            {items.map((item, i) => (
                <li key={i} className="grid grid-cols-[26px_minmax(0,1fr)] items-start gap-3.5">
                    <span className="border-site-border text-site-primary mt-px grid size-[26px] place-items-center rounded-full border bg-white font-mono text-xs">
                        {i + 1}
                    </span>
                    <span className="text-site-body text-[15px] leading-relaxed">{item}</span>
                </li>
            ))}
        </ol>
    );
}

function Fields({ rows }: { rows: [string, ReactNode][] }) {
    return (
        <dl className="border-site-border mt-4 grid max-w-[68ch] grid-cols-1 border-t sm:grid-cols-[minmax(120px,180px)_minmax(0,1fr)]">
            {rows.map(([term, def], i) => (
                <div key={i} className="contents">
                    <dt className="text-site-ink border-site-border pt-2.5 pr-4 font-mono text-xs sm:border-b sm:pb-2.5">{term}</dt>
                    <dd className="text-site-body border-site-border border-b pt-0.5 pb-2.5 text-[15px] sm:pt-2.5">{def}</dd>
                </div>
            ))}
        </dl>
    );
}

function Table({ head, rows }: { head: string[]; rows: ReactNode[][] }) {
    return (
        <div className="border-site-border mt-5 overflow-x-auto rounded border bg-white">
            <table className="w-full min-w-[520px] border-collapse text-sm">
                <thead>
                    <tr>
                        {head.map((h) => (
                            <th
                                key={h}
                                className="border-site-border bg-site-surface text-site-muted border-b px-4 py-2.5 text-left text-[11px] font-semibold tracking-wider whitespace-nowrap uppercase"
                            >
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, i) => (
                        <tr key={i}>
                            {row.map((cell, j) => (
                                <td key={j} className="border-site-border text-site-body border-b px-4 py-2.5 align-top last:border-b-0">
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function P({ children }: { children: ReactNode }) {
    return <p className="text-site-body mt-3.5 max-w-[68ch] text-[15px] leading-relaxed">{children}</p>;
}

function B({ children }: { children: ReactNode }) {
    return <strong className="text-site-ink font-semibold">{children}</strong>;
}

function M({ children }: { children: ReactNode }) {
    return <code className="bg-site-surface text-site-ink rounded px-1.5 py-0.5 font-mono text-[0.85em]">{children}</code>;
}

function Bullets({ items }: { items: ReactNode[] }) {
    return (
        <ul className="mt-3.5 flex max-w-[68ch] list-none flex-col gap-2 p-0">
            {items.map((item, i) => (
                <li key={i} className="grid grid-cols-[14px_minmax(0,1fr)] gap-2.5">
                    <span className="bg-site-primary mt-[9px] size-[5px] rounded-full" />
                    <span className="text-site-body text-[15px] leading-relaxed">{item}</span>
                </li>
            ))}
        </ul>
    );
}

export default function Handbook({ site, menuItems, socialLinks }: Props) {
    return (
        <PublicSiteLayout site={site} menuItems={menuItems} socialLinks={socialLinks} title="Handbook">
            <Head title="Handbook" />

            {/* Masthead. Deliberately restrained — this is a reference document, not a campaign. */}
            <div className="bg-site-primary-deep px-6 py-12 text-white sm:py-14">
                <div className="mx-auto max-w-[1120px]">
                    <p className="text-site-accent font-mono text-[11px] tracking-[0.14em] uppercase">{site.title}</p>
                    <h1 className="font-display mt-3 text-3xl leading-tight font-bold tracking-tight sm:text-5xl">Handbook</h1>
                    <p className="text-site-accent mt-4 max-w-[62ch] text-base sm:text-[17px]">
                        How to submit, review, edit, publish and administer research on this platform — the complete guide for authors,
                        reviewers, editors and site administrators.
                    </p>
                </div>
            </div>

            <div className="mx-auto grid max-w-[1120px] grid-cols-1 items-start gap-x-14 px-6 pb-24 xl:grid-cols-[210px_minmax(0,1fr)]">
                <nav aria-label="Contents" className="border-site-border border-b pt-8 pb-5 xl:sticky xl:top-6 xl:border-b-0 xl:pb-0">
                    <p className="border-site-border text-site-muted mb-2 border-b pb-2.5 pl-2.5 font-mono text-[10px] tracking-[0.14em] uppercase">
                        Contents
                    </p>
                    <div className="flex flex-col gap-0.5">
                        {CONTENTS.map(([num, label, id]) => (
                            <a
                                key={id}
                                href={`#${id}`}
                                className="hover:bg-site-surface text-site-body hover:text-site-ink grid grid-cols-[22px_1fr] gap-2 rounded px-2.5 py-1.5 text-[13px] leading-snug"
                            >
                                <span className="text-site-muted pt-px font-mono text-[11px]">{num}</span>
                                <span>{label}</span>
                            </a>
                        ))}
                    </div>
                </nav>

                <main className="min-w-0 pt-10">
                    {/* ---------- 1 ---------- */}
                    <Section num="1" title="The platform" id="s1">
                        <P>
                            This is the publishing system for {site.title}. It carries a manuscript through its whole life: an author
                            submits it, editors send it out for peer review, reviewers report back, editors decide, and an accepted
                            article is given a file, an identifier and a place in an issue before it appears on the public site.
                        </P>
                        <P>
                            Everything happens in one of two areas. The <B>public site</B> is what readers see — the homepage, the
                            journal, articles, and search — and needs no account. The <B>staff area</B> is everything behind{' '}
                            <B>Staff login</B>, and what you can see there depends entirely on the roles you have been granted.
                        </P>
                        <P>
                            The site is bilingual. The <B>EN / KH</B> switch in the header changes the interface language, and journals
                            and articles can carry titles and abstracts in both languages.
                        </P>
                        <Note label="Two things worth knowing first">
                            <p>
                                <B>Roles are per journal, not per site.</B> Being an editor of one journal gives you nothing in another.
                                And <B>an account on its own can do nothing</B> — registering gets you in the door, but until somebody
                                grants you a role in a journal you cannot even submit to it. See <a href="#s9">§9</a>.
                            </p>
                        </Note>
                    </Section>

                    {/* ---------- 2 ---------- */}
                    <Section num="2" title="Roles and what they can do" id="s2">
                        <P>
                            Four roles cover almost all day-to-day work: <B>Author</B> submits and follows their own manuscripts;{' '}
                            <B>Reviewer</B> answers invitations and files recommendations; <B>Editor</B> assigns reviewers, decides and
                            publishes; <B>Journal Manager</B> does all of that plus people and settings. The rest exist for larger
                            editorial teams and are granted the same way.
                        </P>
                        <Table
                            head={['Role', 'Scope of authority']}
                            rows={[
                                [<B>Journal Manager</B>, "The journal's administrator. People, settings, identifiers, and every editorial action including publish."],
                                [<B>Editor</B>, 'Journal-wide editorial authority. Can decide on any submission and can publish.'],
                                [<B>Section Editor</B>, <>Decides only on submissions they are assigned to. <B>Cannot publish.</B></>],
                                [<B>Guest Editor</B>, <>Decides on submissions in the issue they are assigned to. <B>Cannot publish</B>, even on their own issue.</>],
                                [<B>Reviewer</B>, 'Sees only the submissions they are invited to review.'],
                                [<B>Author</B>, 'Submits, and sees only their own submissions.'],
                                [<B>Copyeditor</B>, 'Works on files during the copyediting stage.'],
                                [<B>Layout Editor</B>, 'Prepares the typeset files (galleys) during production.'],
                                [<B>Proofreader</B>, 'Checks the article before it is published.'],
                                [<B>Auditor</B>, 'Read-only oversight of the editorial record.'],
                                [<B>Reader</B>, 'A registered reader of the journal.'],
                            ]}
                        />
                        <Note label="Above all journals">
                            <p>
                                <B>Site administrators</B> are separate from journal roles. They create journals, control the public
                                site's appearance and content, and configure AI, notifications and file storage. A site administrator can
                                reach every journal without being granted a role in it. See <a href="#s11">§11</a>.
                            </p>
                        </Note>
                    </Section>

                    {/* ---------- 3 ---------- */}
                    <Section num="3" title="Your account" id="s3">
                        <H3>Getting in</H3>
                        <Where>Staff login, top right of every public page</Where>
                        <P>
                            If you do not have an account yet, either register yourself at <M>/register</M> or ask a journal manager to
                            create one for you (<a href="#s9">§9</a>). If you have forgotten your password, use <B>Forgot password</B> on
                            the login screen — a reset link is emailed to you. After logging in you land on your dashboard, which lists
                            the journals you have a role in.
                        </P>

                        <H3>Your profile</H3>
                        <Where>Settings → Profile</Where>
                        <P>
                            Your name and email here are what appear on submissions you author and reviews you file, so keep them
                            accurate. You can also connect your <B>ORCID</B>, which attaches your researcher identifier to the articles
                            you publish.
                        </P>

                        <H3>Notifications</H3>
                        <Where>Settings → Notifications</Where>
                        <P>
                            Choose how the platform reaches you about review invitations, decisions and editorial messages. Each category
                            can be delivered by <B>email</B>, by <B>Telegram</B>, or muted entirely.
                        </P>
                        <P>
                            To use Telegram you link your account once from this screen. Muting a category stops it everywhere — do not
                            mute review invitations if you are an active reviewer, or you will simply stop hearing about them.
                        </P>

                        <H3>Your data</H3>
                        <P>
                            <B>Settings → Data export</B> downloads everything the platform holds about you as a single file — your
                            profile, your submissions, your reviews and your roles.
                        </P>
                    </Section>

                    {/* ---------- 4 ---------- */}
                    <Section num="4" title="Submitting a manuscript" id="s4">
                        <Where>Journal → New submission</Where>
                        <Rule label="Before you start">
                            <p>
                                You must hold a role in the journal you are submitting to. If <B>New submission</B> is missing or refuses
                                you, you have an account but no role yet — ask the journal manager to add you as an Author (
                                <a href="#s9">§9</a>).
                            </p>
                        </Rule>

                        <H3>What to have ready</H3>
                        <Fields
                            rows={[
                                ['Manuscript', 'One file: PDF, DOC, DOCX or ODT. Up to 50 MB.'],
                                ['Title', "The article's full title. Can be entered in English and Khmer."],
                                ['Abstract', 'The full abstract. Also bilingual.'],
                                ['Section', 'Which part of the journal this belongs in — for example Research Articles.'],
                                ['Authors', 'Every contributor: given name, family name, email, affiliation and ORCID.'],
                                ['Cover letter', 'Optional. A message to the editors that is not published.'],
                                ['Supporting files', 'Optional. Datasets, figures, appendices — same file types and size limit.'],
                            ]}
                        />

                        <H3>How it goes</H3>
                        <Steps
                            items={[
                                <>
                                    <B>Choose the file.</B> Drop your manuscript in first. If the journal has the assistant switched on,
                                    it reads the file and fills in the title, abstract and author list for you.
                                </>,
                                <>
                                    <B>Check what it filled in.</B> This is a convenience, not an authority — read every field and
                                    correct it. What you confirm here becomes the published record.
                                </>,
                                <>
                                    <B>Complete the author list.</B> Add every co-author with their affiliation. Affiliations can be
                                    looked up against the global ROR registry so institution names stay consistent.
                                </>,
                                <>
                                    <B>Attach supporting files</B> if you have them, and write a cover letter if you want the editors to
                                    know something the manuscript does not say.
                                </>,
                                <>
                                    <B>Submit.</B> The submission enters the <B>Submission</B> stage and appears in the editors' queue.
                                </>,
                            ]}
                        />

                        <Note label="If the assistant says it could not read your file">
                            <p>
                                Fill the form in by hand and submit as normal. The assistant is optional and never blocks a submission — a
                                manuscript it cannot read is not a manuscript the journal will refuse.
                            </p>
                        </Note>

                        <H3>After you submit</H3>
                        <P>
                            Open your submission from the journal's submissions list to follow it. The <B>Timeline</B> records every step
                            — editor assignments, review rounds, decisions — and the <B>Files</B> panel holds every version.
                        </P>
                        <P>
                            If an editor requests revisions you will be notified. Upload the revised manuscript to the same submission
                            through <B>Files</B>; do not start a new submission, or the review history is lost.
                        </P>
                    </Section>

                    {/* ---------- 5 ---------- */}
                    <Section num="5" title="Reviewing" id="s5">
                        <H3>The invitation</H3>
                        <P>
                            When an editor invites you, you receive a notification with a link and a due date. Accept or decline promptly
                            — a submission cannot move forward while it waits on an answer, and editors can see exactly which invitations
                            are still unanswered.
                        </P>
                        <P>
                            Declining is a normal and useful answer. Decline if the topic is outside your expertise, if you cannot meet
                            the date, or if you have a conflict of interest.
                        </P>

                        <H3>Anonymity</H3>
                        <P>Each journal sets how reviews are conducted, and it affects what you should write:</P>
                        <Fields
                            rows={[
                                ['Anonymous', 'The author does not know who you are. You know who the author is.'],
                                ['Double anonymous', 'Neither side knows the other. Avoid anything in your comments that identifies you.'],
                                ['Open', 'Both sides know. Your name is attached to your review.'],
                            ]}
                        />

                        <H3>Writing the review</H3>
                        <Steps
                            items={[
                                <>
                                    <B>Accept the invitation.</B> The manuscript only becomes available once you have accepted.
                                </>,
                                <>
                                    <B>Download and read the manuscript</B> from the review screen.
                                </>,
                                <>
                                    <B>Complete the review form.</B> The journal may have its own questions — short answers, longer
                                    comments, multiple choice, or ratings.
                                </>,
                                <>
                                    <B>Choose a recommendation</B> from the list below.
                                </>,
                                <>
                                    <B>Submit.</B> Once submitted the review goes to the editor and you cannot edit it, so read it
                                    through first.
                                </>,
                            ]}
                        />

                        <H3>What the recommendations mean</H3>
                        <Table
                            head={['Recommendation', 'Use it when']}
                            rows={[
                                [<B>Accept</B>, 'Publishable as it stands, or with only trivial corrections.'],
                                [<B>Revisions required</B>, 'Publishable once the author fixes specific problems. No second review needed.'],
                                [<B>Resubmit for review</B>, 'The problems are substantial. It could work, but the revised version must be reviewed again.'],
                                [<B>Resubmit elsewhere</B>, 'Sound work, but wrong journal for it.'],
                                [<B>Decline</B>, 'Not publishable, and revision would not change that.'],
                                [<B>See comments</B>, 'Your judgement does not fit the list. Explain it fully in your comments.'],
                            ]}
                        />
                        <Note label="Remember">
                            <p>
                                The recommendation is advice. The editor decides, and may decide differently — often because a second
                                reviewer saw it another way.
                            </p>
                        </Note>
                    </Section>

                    {/* ---------- 6 ---------- */}
                    <Section num="6" title="The editorial workflow" id="s6">
                        <P>
                            Every submission sits at exactly one <B>stage</B>, and moves between stages only by an editor recording a{' '}
                            <B>decision</B>. The platform enforces this: a decision that is not legal from the current stage is not
                            offered, and will be refused if attempted.
                        </P>

                        <figure className="mt-6">
                            <div className="border-site-border overflow-x-auto rounded border bg-white px-4 py-5">
                                <svg viewBox="0 0 880 294" role="img" aria-labelledby="wf-title" className="block h-auto w-full min-w-[660px]">
                                    <title id="wf-title">Submission stages and the decisions that move between them</title>
                                    <defs>
                                        <marker id="arw" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                                            <path d="M 0 0 L 10 5 L 0 10 z" className="fill-site-muted" />
                                        </marker>
                                    </defs>

                                    {[
                                        [8, 'Submission'],
                                        [188, 'Review'],
                                        [368, 'Copyediting'],
                                        [548, 'Production'],
                                    ].map(([x, label]) => (
                                        <g key={label as string}>
                                            <rect x={x as number} y="86" width="140" height="50" rx="4" className="fill-site-surface stroke-site-border" />
                                            <text x={(x as number) + 70} y="116" textAnchor="middle" className="fill-site-ink text-[13px] font-medium">
                                                {label}
                                            </text>
                                        </g>
                                    ))}
                                    <rect x="728" y="86" width="140" height="50" rx="4" className="fill-site-surface-alt stroke-site-primary" />
                                    <text x="798" y="116" textAnchor="middle" className="fill-site-ink text-[13px] font-medium">
                                        Published
                                    </text>

                                    {/* Forward edges. Labels sit above the row so the narrow gaps never clip them. */}
                                    {[
                                        [148, 182, 165, 'send to review'],
                                        [328, 362, 345, 'accept'],
                                        [508, 542, 525, 'send to production'],
                                        [688, 722, 705, 'publish'],
                                    ].map(([x1, x2, mid, label]) => (
                                        <g key={label as string}>
                                            <path d={`M ${x1} 111 L ${x2} 111`} className="stroke-site-muted fill-none" strokeWidth="1.4" markerEnd="url(#arw)" />
                                            <text x={mid as number} y="76" textAnchor="middle" className="fill-site-muted font-mono text-[10.5px]">
                                                {label}
                                            </text>
                                        </g>
                                    ))}

                                    <path d="M 226 86 C 226 40, 290 40, 290 86" className="stroke-site-muted fill-none" strokeWidth="1.4" markerEnd="url(#arw)" />
                                    <text x="258" y="30" textAnchor="middle" className="fill-site-muted font-mono text-[10.5px]">
                                        request revisions · resubmit
                                    </text>

                                    {[78, 258, 438, 618].map((x) => (
                                        <path key={x} d={`M ${x} 136 L ${x} 192`} className="stroke-site-muted fill-none" strokeWidth="1.4" />
                                    ))}
                                    <path d="M 78 192 L 618 192" className="stroke-site-muted fill-none" strokeWidth="1.4" />
                                    <path d="M 348 192 L 348 228" className="stroke-site-muted fill-none" strokeWidth="1.4" markerEnd="url(#arw)" />
                                    <text x="358" y="212" className="fill-site-muted font-mono text-[10.5px]">
                                        decline
                                    </text>

                                    <rect x="278" y="232" width="140" height="46" rx="4" className="fill-amber-50 stroke-amber-300" />
                                    <text x="348" y="260" textAnchor="middle" className="fill-site-ink text-[13px] font-medium">
                                        Declined
                                    </text>
                                </svg>
                            </div>
                            <figcaption className="text-site-muted mt-2.5 max-w-[68ch] text-[13px]">
                                The complete state machine. Any stage before publication can be declined; only Production can be
                                published. A submission in Review can loop back to Review as many times as the revisions need.
                            </figcaption>
                        </figure>

                        <H3>Three rules that will stop you</H3>
                        <P>
                            These are enforced, not advisory. Each one prevents a specific failure, and each produces a clear refusal
                            rather than a silent no-op.
                        </P>
                        <Rule label="Rule 1 — an editor must be assigned">
                            <p>
                                No decision can be recorded on a submission that has nobody assigned to its <em>current</em> stage.
                                Assign an editor first, from the submission's <B>People</B> panel.
                            </p>
                        </Rule>
                        <Rule label="Rule 2 — publishing needs an issue">
                            <p>
                                A submission cannot be published until it has been assigned to an issue. An article with no issue has
                                nowhere to appear.
                            </p>
                        </Rule>
                        <Rule label="Rule 3 — publishing is a higher authority">
                            <p>
                                Only an <B>Editor</B> or <B>Journal Manager</B> can publish. Section Editors and Guest Editors can take
                                every other decision on submissions they are assigned to, but not this one.
                            </p>
                        </Rule>

                        <H3>Returning a manuscript</H3>
                        <P>
                            <B>Return to author</B> hands a manuscript back for corrections without rejecting it — wrong template,
                            missing ethics statement, figures unreadable. It is available from Submission, Screening and Review, and it
                            is the right answer whenever the problem is fixable and the manuscript is not yet ready to go forward.
                        </P>
                        <P>
                            Returning does not move the submission to another stage; it changes <em>whose move it is</em>. The submission
                            leaves your <B>Awaiting your decision</B> list and appears under <B>With the author</B> until they upload a
                            replacement manuscript, at which point it comes straight back to you. A supporting file does not bring it
                            back — only a new manuscript does.
                        </P>

                        <H3>Screening and similarity</H3>
                        <P>
                            A journal can switch on two optional steps in <B>Journal → Settings → Workflow</B>:
                        </P>
                        <Fields
                            rows={[
                                [
                                    'Screening stage',
                                    'Adds a Screening stage between submission and review for the first editorial look and the technical check. With it on, review is reached through screening and cannot be skipped.',
                                ],
                                [
                                    'Similarity check',
                                    'A manuscript cannot be sent to reviewers until a check is recorded as passed. Declining and returning are never blocked by it.',
                                ],
                            ]}
                        />
                        <P>
                            Similarity results are recorded by an editor in the <B>Similarity</B> panel on the submission, from whatever
                            tool your institution licences — Turnitin, iThenticate, Crossref Similarity Check. The platform stores the
                            outcome, the percentage, the tool and optionally the report; <B>it does not run the check itself.</B> A
                            submission can be checked more than once, and the most recent result is the one that counts, so a re-check
                            after corrections clears an earlier flag.
                        </P>
                        <Note label="Why “passed” and not just “checked”">
                            <p>
                                A result of <B>Awaiting report</B> or <B>Flagged</B> does not open the door. Having run a check is not
                                the same as having had it come back clean, and treating them as equivalent is how a screening policy
                                becomes decorative.
                            </p>
                        </Note>

                        <H3>Running a review round</H3>
                        <Steps
                            items={[
                                <>
                                    <B>Open the submission</B> from Journal → Submissions.
                                </>,
                                <>
                                    <B>Assign yourself or another editor</B> in the People panel. Nothing else can happen until this is
                                    done.
                                </>,
                                <>
                                    <B>Record “Send to review”.</B> This opens the first review round.
                                </>,
                                <>
                                    <B>Invite reviewers</B> from “Invite a reviewer”, each with a due date. Invite more than you need —
                                    some will decline.
                                </>,
                                <>
                                    <B>Wait, and watch the dashboard.</B> “Awaiting reviewer response” and “Reviews overdue” tell you who
                                    to chase.
                                </>,
                                <>
                                    <B>Read the reviews</B> as they arrive, under “Review rounds”.
                                </>,
                                <>
                                    <B>Record a decision.</B> Write the reason — it is sent to the author, and it is the only explanation
                                    they get.
                                </>,
                            ]}
                        />

                        <H3>The decisions</H3>
                        <Table
                            head={['Decision', 'From', 'To', 'Meaning']}
                            rows={[
                                [<B>Send to screening</B>, 'Submission', 'Screening', 'Only when the journal screens.'],
                                [<B>Send to review</B>, 'Submission or Screening', 'Review', 'Opens peer review.'],
                                [<B>Return to author</B>, 'Submission, Screening, Review', 'unchanged', 'Back for corrections. Not a rejection.'],
                                [<B>Request revisions</B>, 'Review', 'Review', 'Author revises; no further review needed.'],
                                [<B>Resubmit</B>, 'Review', 'Review', 'Author revises and it goes out for review again.'],
                                [<B>Accept</B>, 'Review', 'Copyediting', 'Accepted for publication.'],
                                [<B>Send to copyediting</B>, 'Review', 'Copyediting', 'Same move, without recording an acceptance.'],
                                [<B>Send to production</B>, 'Copyediting', 'Production', 'Text is final; prepare the files.'],
                                [<B>Publish</B>, 'Production', 'Published', 'Goes live. Editor or Journal Manager only.'],
                                [<B>Decline</B>, 'Any stage before Published', 'Declined', 'Ends the submission.'],
                            ]}
                        />

                        <Note label="Two-person approval">
                            <p>
                                A journal can require that <B>Accept</B> and <B>Decline</B> are countersigned by a second editor. When
                                this is switched on, the first editor's decision is recorded and held until a second editor confirms it.
                                It is off unless a journal manager turns it on.
                            </p>
                        </Note>

                        <H3>Your queue</H3>
                        <Where>Journal → Dashboard</Where>
                        <P>The journal dashboard is a work list, not a summary. Each figure links to the submissions behind it:</P>
                        <Bullets
                            items={[
                                <>
                                    <B>No editor assigned</B> — new submissions nobody is looking after. Clear this first; nothing can
                                    move until an editor is assigned.
                                </>,
                                <>
                                    <B>Awaiting your decision</B> — submissions sitting at a stage that needs you.
                                </>,
                                <>
                                    <B>Awaiting reviewer response</B> — invitations neither accepted nor declined.
                                </>,
                                <>
                                    <B>Reviews overdue</B> — accepted assignments past their due date.
                                </>,
                                <>
                                    <B>Ready to publish</B> — in production, assigned to an issue, nothing left to do.
                                </>,
                            ]}
                        />
                    </Section>

                    {/* ---------- 7 ---------- */}
                    <Section num="7" title="Production and publishing" id="s7">
                        <P>
                            Once a submission reaches <B>Production</B> there are three things to do before it can go live: attach the
                            file readers will download, give it an identifier, and put it in an issue.
                        </P>

                        <H3>Galleys — the published file</H3>
                        <Where>Submission → Galleys</Where>
                        <P>
                            A galley is the finished, formatted file a reader downloads. It is not the author's manuscript — it is the
                            typeset version. Upload it with a <B>format label</B> (“PDF”, “XML”) which is what the reader sees on the
                            download button.
                        </P>
                        <P>
                            Accepted formats: <M>PDF</M>, <M>HTML</M>, <M>XML</M>, <M>EPUB</M>. You can attach more than one, and remove
                            any that is wrong.
                        </P>
                        <Rule label="Check before you publish">
                            <p>
                                The platform does not currently require a galley before publishing. An article published without one has
                                a page and a citation but nothing to read — so confirm the galley is attached and downloads correctly
                                before you take the publish decision.
                            </p>
                        </Rule>

                        <H3>DOI</H3>
                        <P>
                            A DOI is the permanent identifier a citation points at. Once the journal has a DOI prefix configured (
                            <a href="#s10">§10</a>) you can either <B>generate</B> one from the journal's pattern or <B>type one in</B> if
                            it was issued elsewhere.
                        </P>
                        <P>
                            Generating uses the journal's pattern, so identifiers stay consistent across the whole journal. You can edit a
                            DOI afterwards, but once an article is published and cited a changed DOI breaks those citations — so get it
                            right before publishing.
                        </P>

                        <H3>Assign to an issue</H3>
                        <P>
                            In the submission's <B>Issue</B> panel, choose the issue this article belongs to. This is required before
                            publishing. If the right issue does not exist, create it first (<a href="#s8">§8</a>).
                        </P>

                        <H3>Publish</H3>
                        <Steps
                            items={[
                                <>
                                    <B>Confirm the galley downloads</B> and is the right file.
                                </>,
                                <>
                                    <B>Confirm the metadata</B> — title, abstract, and every author with the right affiliation. This
                                    becomes the public record and the citation.
                                </>,
                                <>
                                    <B>Confirm the DOI and the issue.</B>
                                </>,
                                <>
                                    <B>Record the “Publish” decision.</B> The article appears immediately on the public site, in search,
                                    and in the citation exports.
                                </>,
                            ]}
                        />
                    </Section>

                    {/* ---------- 8 ---------- */}
                    <Section num="8" title="Issues" id="s8">
                        <Where>Journal → Issues</Where>
                        <P>
                            An issue collects articles under a volume, number and year — <em>Vol. 1, No. 1, 2026</em>. Articles are
                            assigned to an issue during production, and an issue can be prepared quietly and released when it is ready.
                        </P>

                        <H3>Creating one</H3>
                        <Fields
                            rows={[
                                ['Volume', 'The volume number.'],
                                ['Number', 'The issue number within that volume.'],
                                ['Year', 'The year of publication.'],
                                ['Title', 'Optional, for special or themed issues.'],
                                ['Publish date', 'When it is dated as published.'],
                            ]}
                        />

                        <H3>Issue galleys</H3>
                        <P>
                            An issue can carry its own file — a complete PDF of the whole issue, for example — attached from the issue's
                            page. Accepted formats: <M>PDF</M>, <M>HTML</M>, <M>XML</M>.
                        </P>

                        <H3>Publishing an issue</H3>
                        <P>
                            Publishing an issue makes it visible on the public site. This is separate from publishing the individual
                            articles in it — an article becomes public through its own <B>Publish</B> decision (<a href="#s7">§7</a>).
                        </P>
                    </Section>

                    {/* ---------- 9 ---------- */}
                    <Section num="9" title="People and invitations" id="s9">
                        <Where>Journal → People</Where>
                        <P>
                            This screen is where roles are granted, and it is the answer to most “I can't see anything” problems. It lists
                            current members with their roles, and any invitations still outstanding.
                        </P>

                        <H3>Three ways to add somebody</H3>
                        <Steps
                            items={[
                                <>
                                    <B>Grant a role to an existing user.</B> They already have an account — find them and give them a role
                                    in this journal.
                                </>,
                                <>
                                    <B>Create the account yourself.</B> Enter their full name, email and role. Use this for staff who are
                                    not on the system yet and whom you can tell directly.
                                </>,
                                <>
                                    <B>Send an invitation.</B> They receive a link, follow it, set their own password, and arrive with
                                    the role already attached. Best for external reviewers.
                                </>,
                            ]}
                        />

                        <Note label="One person, several roles">
                            <p>
                                Roles add up rather than replace one another. Someone can be an Author and a Reviewer in the same journal.
                                Removing one role leaves the others intact.
                            </p>
                        </Note>

                        <H3>Removing access</H3>
                        <P>
                            Removing a role withdraws that access immediately. Work they have already done — submissions, reviews,
                            recorded decisions — stays in the record; the editorial history is never rewritten by a change of staff.
                        </P>
                    </Section>

                    {/* ---------- 10 ---------- */}
                    <Section num="10" title="Journal settings" id="s10">
                        <Where>Journal → Settings</Where>
                        <P>
                            This is the journal's publishing identity — the details that make it citable and indexable. They appear on the
                            public journal page and in the metadata that indexing services read.
                        </P>
                        <Fields
                            rows={[
                                ['Publisher', 'The publishing body.'],
                                ['ISSN (online)', <>The ISSN for the electronic edition. Eight characters as <M>1234-5678</M>.</>],
                                ['ISSN (print)', 'The ISSN for the printed edition, if there is one.'],
                                ['DOI prefix', <>Your registered prefix, in the form <M>10.xxxxx</M>. Issued by a registration agency such as Crossref.</>],
                                ['DOI pattern', "How the rest of each DOI is built. The screen previews what a real article's DOI would look like."],
                            ]}
                        />
                        <Note label="Both are applied for, not chosen">
                            <p>
                                An ISSN comes from an ISSN centre and a DOI prefix from a registration agency you have an account with.
                                Enter them here only once they have actually been issued to this journal — publishing a number you were
                                not assigned makes every citation to it wrong.
                            </p>
                        </Note>
                        <P>
                            Settings can be filled in at any time, including after articles are published. If you add a DOI prefix later,
                            existing articles will not have DOIs until you generate one for each of them (<a href="#s7">§7</a>).
                        </P>
                    </Section>

                    {/* ---------- 11 ---------- */}
                    <Section num="11" title="Site administration" id="s11">
                        <P>
                            Site administration covers everything above the individual journal: creating journals, the look and content of
                            the public site, and the three system services. It is reached from the left sidebar after logging in.
                        </P>

                        <H3>Journals</H3>
                        <Fields
                            rows={[
                                ['Title', "The journal's name, per language."],
                                ['Path', <>The short code in its web address — <M>biu</M> gives <M>/journal/biu</M>. Choose carefully; changing it later breaks existing links.</>],
                                ['Primary locale', 'The language used when a title has not been translated.'],
                                ['Manager', 'The journal manager, who can then add everybody else.'],
                                ['Enabled', "Whether it appears publicly. A disabled journal's pages return “not found”, and its articles are excluded from search."],
                            ]}
                        />

                        <H3>The public site — CMS</H3>
                        <P>Eight screens control what visitors see. None of them require touching code.</P>
                        <Table
                            head={['Screen', 'Controls']}
                            rows={[
                                [<B>Appearance</B>, 'The landing-page template (Academic, Bold, Classic, Magazine or Minimal), the typeface, and the colour palette.'],
                                [<B>Homepage</B>, 'Site title, tagline, the About text, the highlight panels, and the site logo. Can also redirect the homepage straight to a single journal.'],
                                [<B>Hero slides</B>, 'The rotating images at the top of the homepage, with their captions.'],
                                [<B>Indexing &amp; tools</B>, 'The “Indexed by” and “Tools” logo strips — label, logo and link for each.'],
                                [<B>Menu</B>, "The header navigation: each item's label and address, in order."],
                                [<B>Pages</B>, <>Standalone pages — submission guidelines, ethics, contact — each at <M>/pages/{'{slug}'}</M>.</>],
                                [<B>Editorial board</B>, 'The public board: groups, and members with photo, role and affiliation.'],
                                [<B>Footer &amp; social</B>, 'Footer text and social media links.'],
                            ]}
                        />
                        <Rule label="“Indexed by” is a factual claim">
                            <p>
                                Listing a service there tells authors and readers that this journal's articles can be found there. It
                                starts empty on purpose and nothing fills it automatically. Add an entry only once the journal is
                                genuinely indexed by that service — false indexing claims are the hallmark of predatory journals and will
                                damage the university's standing.
                            </p>
                        </Rule>

                        <H3>AI</H3>
                        <P>
                            The assistant that pre-fills the submission form from an uploaded manuscript (<a href="#s4">§4</a>). This
                            screen holds the provider API key, the switch that turns the feature on per journal, and a log of every run
                            with its token use and cost.
                        </P>
                        <P>
                            Use <B>Test</B> after entering a key. If the feature is switched on without a working key, authors are told
                            their manuscript could not be read — which blames a good file for a configuration problem.
                        </P>

                        <H3>Indexing</H3>
                        <P>
                            Credentials for depositing published articles to <B>Crossref</B> and <B>DataCite</B>. Deposit is automatic:
                            when an editor publishes an article it is sent, with no further action. It only happens for a journal that
                            has a <B>DOI prefix</B> in its own settings (<a href="#s10">§10</a>), and the screen lists every journal with
                            whether it is ready.
                        </P>
                        <Rule label="Test and live are not the same endpoint">
                            <p>
                                Both agencies run a sandbox that accepts a deposit, answers “submitted”, and registers nothing. An
                                installation left on <B>Test</B> looks healthy indefinitely — the failure only shows up when somebody
                                clicks a DOI months later and it does not resolve. New installations start on Test on purpose; move the
                                switch to <B>Live</B> only when you intend real registrations.
                            </p>
                        </Rule>
                        <P>
                            Use <B>Test credentials</B> after entering a login. Leaving the password box blank keeps the password already
                            saved, so you can correct the depositor email without wiping the credential.
                        </P>
                        <P>
                            The same screen shows the <B>OAI-PMH</B> address. Services that harvest rather than receive deposits —
                            WorldCat, BASE, CORE, OpenAIRE — need only that address, and it works with no credentials at all.
                        </P>
                        <H3>Notifications</H3>
                        <P>
                            How the platform delivers messages. Configure email and, optionally, a Telegram bot. Both have a test action —
                            send a test message before relying on either, because a silent delivery failure looks exactly like a reviewer
                            ignoring you.
                        </P>

                        <H3>Storage</H3>
                        <P>
                            Where uploaded files are kept. By default everything is stored on the server itself. You can connect cloud
                            object storage instead, and move the existing files across.
                        </P>
                        <Steps
                            items={[
                                <>
                                    <B>Enter the bucket details</B> and use Test to confirm the connection works.
                                </>,
                                <>
                                    <B>Switch the destination</B> so new uploads go to the bucket.
                                </>,
                                <>
                                    <B>Move existing files.</B> Each file is copied, verified in its new home, and only then does the
                                    database point at the new location.
                                </>,
                            ]}
                        />
                        <Note label="Nothing is deleted">
                            <p>
                                The migration copies and re-points; it never removes the original. If something goes wrong the local copy
                                is still there. Once you have confirmed that manuscripts and galleys download correctly from the bucket,
                                the old files can be cleaned up separately.
                            </p>
                        </Note>

                        <H3>Accounts and access</H3>
                        <Rule label="Check this on any new installation">
                            <p>
                                Demonstration accounts created during setup use well-known passwords and are full site administrators.
                                Before a site is announced publicly, change those passwords or remove the accounts — a live site with a
                                seeded <M>admin@example.com</M> still active is an open door.
                            </p>
                        </Rule>
                        <P>
                            There is no site-wide list of users. Accounts are managed per journal, in <B>Journal → People</B> (
                            <a href="#s9">§9</a>) — which is also the only place a role can be granted or withdrawn. An administrator
                            cannot reset somebody else's password from the interface; the person uses <B>Forgot password</B> themselves.
                        </P>
                        <Note label="Suspending an account">
                            <p>
                                Suspension — blocking a login while leaving all the person's work in place — exists in the platform but
                                has <B>no screen yet</B>. Until it has one, withdrawing every role in <B>Journal → People</B> is how you
                                remove somebody's access.
                            </p>
                        </Note>
                    </Section>

                    {/* ---------- 12 ---------- */}
                    <Section num="12" title="The public site" id="s12">
                        <P>Everything in this section is open to anyone, with no account.</P>

                        <H3>Search</H3>
                        <Where>The search icon in the header · /search</Where>
                        <P>
                            Searching from the box matches the title, abstract, authors and DOI of every published article across every
                            journal. Unpublished manuscripts never appear.
                        </P>
                        <P>
                            <B>Advanced search</B> narrows by specific field. Filling in more than one field narrows the result further
                            rather than widening it:
                        </P>
                        <Fields
                            rows={[
                                ['Title', 'Words in the article title.'],
                                ['Author', 'Name, affiliation or ORCID. Either name order works.'],
                                ['Abstract', 'Words in the abstract.'],
                                ['DOI', 'Whole or partial DOI.'],
                                ['Journal', 'Restrict to one journal.'],
                                ['Section', 'Restrict to a section — offered once a journal is chosen.'],
                                ['Year from / to', 'A publication year range.'],
                                ['Sort', 'Relevance, newest, oldest, or title.'],
                            ]}
                        />
                        <P>
                            A search is fully described by its web address, so a useful search can be bookmarked or sent to a colleague.
                        </P>

                        <H3>Article pages</H3>
                        <P>
                            Each published article has a permanent page carrying its title, authors, abstract, issue, section, DOI, and
                            the download button for its galley.
                        </P>
                        <P>
                            <B>Cite this</B> exports the citation in three formats — <B>BibTeX</B>, <B>RIS</B> and <B>CSL-JSON</B> —
                            which import directly into Zotero, Mendeley, EndNote and LaTeX.
                        </P>

                        <H3>Harvesting and indexing</H3>
                        <P>
                            The platform exposes an <B>OAI-PMH</B> endpoint at <M>/oai</M>, the standard interface indexing services use
                            to collect metadata about published articles. Give this address to any indexing service that asks how to
                            harvest the journal.
                        </P>
                        <P>
                            Editors can also export a journal's published articles as a spreadsheet from <B>Journal → Export articles</B>.
                        </P>
                    </Section>

                    {/* ---------- 13 ---------- */}
                    <Section num="13" title="Reference" id="s13">
                        <H3>Files you can upload</H3>
                        <Table
                            head={['What', 'Formats', 'Limit']}
                            rows={[
                                [<B>Manuscript</B>, 'pdf · doc · docx · odt', '50 MB'],
                                [<B>Supporting files</B>, 'pdf · doc · docx · odt', '50 MB each'],
                                [<B>Article galley</B>, 'pdf · html · xml · epub', '50 MB'],
                                [<B>Issue galley</B>, 'pdf · html · xml', '50 MB'],
                                [<B>Logos and badges</B>, 'png · jpg · svg · webp', '5 MB'],
                                [<B>Hero slides, board photos</B>, 'png · jpg · webp', '5 MB'],
                            ]}
                        />

                        <H3>Stages at a glance</H3>
                        <Table
                            head={['Stage', 'What is happening', 'Who is waiting']}
                            rows={[
                                [<B>Submission</B>, 'Just arrived. Needs an editor.', 'Editor'],
                                [<B>Screening</B>, 'First editorial look and the technical check. Optional.', 'Editor'],
                                [<B>Review</B>, 'Out with reviewers, or with the author for revisions.', 'Reviewers, then the editor'],
                                [<B>Copyediting</B>, 'Accepted. Text is being finalised.', 'Copyeditor'],
                                [<B>Production</B>, 'Galley, DOI and issue are being prepared.', 'Layout editor, then the editor'],
                                [<B>Published</B>, 'Live on the public site.', 'Nobody'],
                                [<B>Declined</B>, 'Ended. Not published.', 'Nobody'],
                            ]}
                        />

                        <H3>Where things live</H3>
                        <Table
                            head={['Screen', 'Address']}
                            rows={[
                                ['Search', <M>/search</M>],
                                ['This handbook', <M>/handbook</M>],
                                ['A journal', <M>/journal/{'{path}'}</M>],
                                ['An article', <M>/journal/{'{path}'}/article/{'{id}'}</M>],
                                ['Standalone page', <M>/pages/{'{slug}'}</M>],
                                ['Your dashboard', <M>/dashboard</M>],
                                ['Journal dashboard', <M>/journal/{'{path}'}/dashboard</M>],
                                ['Submissions', <M>/journal/{'{path}'}/submissions</M>],
                                ['New submission', <M>/journal/{'{path}'}/submissions/create</M>],
                                ['Issues', <M>/journal/{'{path}'}/issues</M>],
                                ['People', <M>/journal/{'{path}'}/people</M>],
                                ['Journal settings', <M>/journal/{'{path}'}/settings</M>],
                                ['Metadata harvesting', <M>/oai</M>],
                            ]}
                        />
                    </Section>

                    {/* ---------- 14 ---------- */}
                    <Section num="14" title="When things stop" id="s14">
                        <Table
                            head={['What you see', 'What it means, and what to do']}
                            rows={[
                                [
                                    <B>I can't find “New submission”</B>,
                                    <>
                                        You have an account but no role in that journal. Ask the journal manager to add you as an Author (
                                        <a href="#s9">§9</a>).
                                    </>,
                                ],
                                [
                                    <B>The decision I want isn't offered</B>,
                                    <>
                                        It is not legal from the current stage. Check the table in <a href="#s6">§6</a> — for example, you
                                        cannot accept a submission that was never sent to review.
                                    </>,
                                ],
                                [
                                    <B>“No assigned editor on this stage”</B>,
                                    <>
                                        Nobody is assigned to the stage the submission is on <em>now</em>. Assign an editor in the
                                        submission's People panel, then decide.
                                    </>,
                                ],
                                [
                                    <B>Publish is refused</B>,
                                    <>
                                        One of three things: the submission is not in Production, it has no issue assigned, or you are a
                                        Section or Guest Editor. See <a href="#s6">§6</a>.
                                    </>,
                                ],
                                [
                                    <B>My upload is rejected</B>,
                                    <>
                                        Wrong format or over the size limit — see <a href="#s13">§13</a>. For a large manuscript, compress
                                        embedded images before converting to PDF.
                                    </>,
                                ],
                                [
                                    <B>“We couldn't read this manuscript”</B>,
                                    <>
                                        The assistant could not extract the metadata. Fill the form in by hand — the submission itself is
                                        unaffected. If it happens to everyone, the API key needs checking (<a href="#s11">§11</a>).
                                    </>,
                                ],
                                [
                                    <B>A published article isn't in search</B>,
                                    <>
                                        Search only returns articles whose submission reached Published <em>and</em> whose journal is
                                        enabled. Check both.
                                    </>,
                                ],
                                [
                                    <B>Reviewers say they got nothing</B>,
                                    <>
                                        Check delivery is configured and send a test message (<a href="#s11">§11</a>), then have the
                                        reviewer check their notification preferences aren't muted (<a href="#s3">§3</a>).
                                    </>,
                                ],
                                [
                                    <B>An article page has no download</B>,
                                    <>
                                        It was published without a galley. Attach one from the submission's Galleys panel; it appears on
                                        the article page immediately.
                                    </>,
                                ],
                            ]}
                        />

                        <div className="border-site-border mt-10 max-w-[68ch] border-t pt-6">
                            <p className="text-site-muted text-[15px]">
                                Anything not covered here goes to your journal manager, who can reach a site administrator if it turns out
                                to be a platform question rather than an editorial one.
                            </p>
                            <Link href="/search" className="text-site-primary mt-3 inline-block text-[15px] font-medium hover:underline">
                                Search the published articles →
                            </Link>
                        </div>
                    </Section>
                </main>
            </div>
        </PublicSiteLayout>
    );
}
