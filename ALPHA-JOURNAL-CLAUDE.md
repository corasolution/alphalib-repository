# CLAUDE.md — Alpha Journal

> Scholarly journal publishing platform. Functional replacement for PKP's Open Journal Systems (OJS 3.5), rebuilt clean-room on the Corasoft stack with an AI editorial layer.
> Brand line: **Alphalib**. Sibling product to Alpha Repository (DSpace replacement) — both sit on **Alpha Scholar Core**.

---

## 0. Legal boundary — read first

OJS is **GPL-3.0**. Do **not** copy, port, translate, or paste OJS/PKP source code, database schema DDL, XSLT, or template markup into this codebase.

**Permitted sources of truth:** public feature docs (docs.pkp.sfu.ca), published specifications (OAI-PMH 2.0, Crossref schema, JATS 1.3, ORCID API, COUNTER R5), and the *shape* of OJS's XML import/export format as observed from exported files.

**Reason:** forking would make Alpha Journal GPL and inherit a 25-year PHP codebase. We want the freedom to license the hosted/AI tier separately.

**One exception, deliberate:** the OJS Native XML import format must be read and written byte-compatibly. Implement it from inspection of real export files, not from PKP's parser code.

---

## 1. Positioning

| | OJS | Alpha Journal |
|---|---|---|
| Editorial UI | Dense, 2016-era, grid-heavy | Modern React 19 + Inertia, task-first |
| Production/galley | Manual DOCX → PDF/HTML, hours per article | AI-assisted JATS XML + galley generation |
| Reviewer finding | Keyword search + editor's memory | Semantic matching w/ COI graph exclusion |
| Integrity screening | External plugins, mostly paid | Built-in advisory screening |
| Multilingual | Full UI/metadata i18n | Same + AI translation + Khmer TTS |
| Target buyer | Global | SEA universities & societies first |

**Beachhead:** Cambodian, Lao, Vietnamese and Thai university journals currently on unmaintained OJS 2.x/3.2 installs. Sell migration + hosting, not software.

**Business model:** open-core. Core platform free to self-host (Apache-2.0 or BSL — decide before first public tag). Corasoft sells hosting, migration, AI credits, DOI registration service, and DOAJ/indexing consulting.

**Non-goals for v1:** plugin gallery/marketplace, theme marketplace, monograph publishing (OMP), preprint server (OPS), subscription/paywall commerce, APC invoicing/payments. Do not build these. Assume open access.

---

## 2. Architecture

### Stack (Corasoft standard — no deviation without a written reason)
- Laravel 13 (PHP 8.4)
- PostgreSQL 16 + `pgvector` + `pg_trgm`
- React 19 + Inertia.js v2 + TypeScript
- Tailwind v4, custom component library
- Cloudflare R2 (submission files, galleys, covers)
- Meilisearch or Postgres FTS for reader-facing search — start with Postgres FTS, promote only if needed
- Laravel Horizon + Redis for queues
- Claude API: Haiku for triage/high-volume, Sonnet for quality-critical tasks
- Azure `km-KH` TTS for Khmer audio galleys

### Hard rules
- **NO FilamentPHP.** All admin UI is custom React/Inertia.
- **Single-tenant per installation.** No `stancl/tenancy`. One institution = one deployment = one database. A single installation may host **many journals** (this is the OJS "multi-journal" model and is required) — implemented as a `journal_id` scope column, not tenancy.
- All journal-scoped queries go through a `JournalScope` global scope. Never trust a raw `journal_id` from the request.
- Every editorial state change writes an immutable `activity_log` row. This is a compliance requirement, not a nice-to-have — editors defend decisions with it.
- Publications are **versioned**. Never mutate a published record; create a new version and keep the old one resolvable.

### Alpha Scholar Core (shared with Alpha Repository)
Extract these as a Composer package `corasoft/alpha-scholar-core` from day one:
- Identity: users, ORCID OAuth, ROR affiliation lookup
- Metadata: multilingual JSONB value objects, CSL-JSON references, contributor model
- Persistent IDs: DOI minting + Crossref/DataCite deposit
- Harvesting: OAI-PMH 2.0 provider
- Files: R2 storage, format detection, checksums, virus scan hook
- Search: embeddings + pgvector
- AI: prompt registry, model router, audit log, per-tenant policy toggles

If you find yourself writing something in this list inside the Alpha Journal app namespace, stop and put it in the core package.

---

## 3. Domain model

```
Site
 └── Journal (context)          # many per installation
      ├── JournalSetting        # multilingual JSONB
      ├── Section               # "Articles", "Reviews", "Editorials"
      ├── Issue
      │    └── IssueGalley
      ├── Submission
      │    ├── SubmissionFile   # versioned, stage-tagged
      │    ├── Publication      # VERSIONED metadata record
      │    │    ├── Contributor # ordered, w/ ORCID + ROR
      │    │    ├── Galley      # PDF / HTML / JATS / XML / audio
      │    │    ├── Citation    # CSL-JSON
      │    │    └── Category / Keyword / Subject
      │    ├── ReviewRound
      │    │    └── ReviewAssignment
      │    ├── EditorialDecision
      │    ├── StageAssignment  # user + role, per stage
      │    ├── Query            # threaded discussion, per stage
      │    └── AiScreeningRun   # ← our addition
      ├── ReviewForm
      │    └── ReviewFormElement
      ├── Announcement
      └── NavigationMenu / StaticPage
```

### Multilingual metadata
Every user-visible string on Journal, Section, Issue, Publication, and Contributor is a JSONB language map:
```json
{ "en": "Cambodian Journal of Agriculture", "km": "ទស្សនាវដ្ដីកសិកម្មកម្ពុជា" }
```
Locale fallback chain: requested → journal primary → site primary. Never render a raw JSON blob. Build a `MultilingualCast` and a `<MultilingualField>` React input that renders one tab per enabled locale.

### Workflow state machine

```
SUBMISSION → REVIEW → COPYEDITING → PRODUCTION → PUBLISHED
                ↓
            DECLINED (from any stage)
```

- REVIEW supports **multiple rounds**. Each round has its own file set and assignments.
- Review types: `anonymous` (reviewer hidden), `double_anonymous`, `open`.
- Reviewer recommendations: accept / revisions_required / resubmit_for_review / resubmit_elsewhere / decline / see_comments.
- Editor decisions: send_to_review / accept / request_revisions / resubmit / decline / send_to_copyediting / send_to_production / publish.
- A submission can only advance if the current stage has at least one assigned editor. Enforce in the state machine, not the UI.

Implement as an explicit `SubmissionWorkflow` service with guarded transitions. Every transition emits an event; notifications and AI hooks subscribe to events, never call the workflow directly.

---

## 4. Roles & permissions

Roles are **per-journal**, not global. A user can be an Author on Journal A and an Editor on Journal B.

| Role | Scope |
|---|---|
| Site Admin | installation-wide |
| Journal Manager | full journal config, users |
| Editor | all submissions, all decisions |
| Section Editor | submissions in assigned sections |
| Guest Editor | submissions in an assigned issue only |
| Reviewer | own assignments only |
| Author | own submissions only |
| Copyeditor / Layout Editor / Proofreader | assigned submissions, assigned stage |
| **Auditor** | **read-only, journal-wide: activity log, decisions, AI runs. No write actions anywhere.** |
| Reader | public + subscribed content |

Use Laravel policies backed by `stage_assignments`. **Anonymity is a security boundary:** in double-anonymous review, reviewer identity must be stripped at the API serializer layer, not hidden in the frontend. Write a test for this. Every leak here is a reputational incident for a customer.

### 4.1 Approval authority

Approval is not one sign-off — it is the `EditorialDecision` chain. Rules:

- Only **Editor** (journal-wide) and **Section Editor** (own sections only) can record a decision. Guest Editor: assigned issue only.
- A decision requires an assigned editor on the current stage. The state machine rejects the transition otherwise — enforce in `SubmissionWorkflow`, never in the UI.
- **Publish** is a separate, higher-privilege action from **accept**. Accepting moves it to copyediting; publishing makes it public and triggers DOI deposit. Require Editor or Journal Manager for publish, and require the issue to be assigned first.
- Optional per-journal setting: `require_dual_approval` — a second editor must countersign accept/decline. Off by default; some societies and most government-funded journals require it.
- Every decision stores: actor, role used, timestamp, target state, reason text, and the file versions in scope at that moment.

### 4.2 Audit account

A dedicated **Auditor** role with no write path anywhere in the system. Intended for research-integrity officers, university QA units, and external accreditation reviewers.

- Sees: full `activity_log`, every `EditorialDecision` with reason text, review assignments and turnaround times, `ai_runs` (what AI produced and whether a human accepted it), version history, and submission-level timelines.
- Cannot see reviewer identities in double-anonymous journals unless the Journal Manager grants `auditor_can_unmask` — and granting it is itself logged.
- Dedicated read-only screen: per-submission timeline, per-editor decision statistics, overdue reviews, and an exportable audit report (PDF/CSV) for accreditation.
- Enforce at the policy layer with a blanket deny on all write abilities, plus a test asserting the Auditor role cannot mutate any model.

**Why this matters commercially:** OJS has no equivalent. Cambodian and regional universities under accreditation review need to prove editorial decisions were made properly. This is a procurement checkbox we can win on.

---

## 5. Interoperability layer — the moat

**This section is non-negotiable and is what makes the product sellable.** Build it in Phase 2, before the AI layer. A journal that cannot get indexed is worthless regardless of how good the editor UX is.

### 5.1 OAI-PMH 2.0 provider
Endpoint: `/oai`. Verbs: `Identify`, `ListMetadataFormats`, `ListSets`, `ListIdentifiers`, `ListRecords`, `GetRecord`.
Formats: `oai_dc` (mandatory), `jats`, `mods`, `rfc1807`. Sets by journal and by section. Resumption tokens with a 24h TTL. Deleted-record policy: `persistent`.
Validate against the OpenAIRE validator before calling it done.

### 5.2 Crossref
- Deposit XML per Crossref schema 5.3.x, `journal_article` type.
- Include: DOI, title, contributors w/ ORCID + affiliation ROR, abstract (JATS), publication dates, pages, license (`AccessIndicators`), full-text resource links, and **reference list** (deposited references massively improve discovery).
- Async deposit queue with status polling and retry. Surface deposit status per article in the editor UI — this is a top OJS complaint.
- Support DOI patterns: `10.PREFIX/journalPath.vVOL.iISS.aID` configurable per journal.

### 5.3 DOAJ
Article upload via DOAJ API v4 on publish. Also produce the DOAJ application metadata bundle (a report the journal manager can hand to DOAJ during application) — this is a real, unmet pain point.

### 5.4 Google Scholar
Emit exact meta tags on every article landing page: `citation_journal_title`, `citation_publisher`, `citation_title`, `citation_author`, `citation_author_institution`, `citation_publication_date`, `citation_volume`, `citation_issue`, `citation_firstpage`, `citation_lastpage`, `citation_doi`, `citation_issn`, `citation_pdf_url`, `citation_abstract_html_url`.
Rules: PDF must be directly linkable, no JS wall, no login. Article landing pages must be server-rendered by Inertia SSR. **Test with the Scholar inclusion checklist before launch.**

### 5.5 Other
- **ORCID:** OAuth authenticate flow (not just typed-in IDs). Store authenticated flag. Push works to ORCID record on publish.
- **ROR:** affiliation autocomplete against the ROR API, store ROR ID alongside free-text.
- **JATS 1.3:** full-text XML as a first-class galley type, with an HTML renderer.
- **COUNTER R5:** views/downloads per article, bot filtering by the COUNTER robot list, SUSHI endpoint.
- **Preservation:** we cannot join PKP PN. Ship Internet Archive deposit + a scheduled BagIt/OAIS export to R2 + institutional dark archive. Document this in sales material as an equivalent, not a gap.
- **Export formats:** OJS Native XML, JATS, Crossref XML, DataCite XML, CSV, BagIt.

### 5.6 Migration importer — the sales weapon
Two paths, both required:
1. **OJS Native XML import** — accept the file OJS itself exports. Map issues, sections, submissions, publications, contributors, galleys, files.
2. **Direct DB reader** — connect to a live OJS 3.x MySQL/Postgres database read-only and pull everything, including review history, decisions, and users. Most institutions have a broken install and no working export.

Requirements: preserve DOIs, preserve **URL paths** (`/index.php/journal/article/view/123` must 301 to the new URL — broken links destroy citations), preserve submission IDs where possible, produce a reconciliation report showing counts in vs counts out, and be idempotent/re-runnable.

Ship a `alpha:migrate-ojs --dry-run` command that outputs a fidelity report. Sales runs this before quoting.

---

## 6. AI layer

Namespace: `Alpha\Journal\Ai`. All AI is **advisory**. Every AI output is labeled, attributable, and dismissible by a human.

### Global guardrails (implement as middleware, not convention)
1. **AI never makes an editorial decision.** No auto-reject, no auto-accept, no auto-assignment. It ranks, flags, drafts, and extracts.
2. **Unpublished manuscripts are confidential.** Use the Anthropic API with zero-retention posture. Document this in the DPA — universities will ask. Provide a per-journal master switch that disables all AI processing of unpublished content.
3. **Every AI call is logged** to `ai_runs`: journal, submission, feature, model, prompt version, token counts, cost, latency, and whether a human accepted or rejected the output. This table is the product's improvement loop and the customer's audit trail.
4. **Prompt registry with versions.** Prompts live in `resources/ai/prompts/{feature}/v{n}.md`, never inline in PHP.
5. **Model routing:** Haiku for extraction, classification, triage, and anything running on every submission. Sonnet for reviewer matching rationale, JATS structuring, translation, and anything a human will read closely.

### Features, in build order

**A1. Metadata extraction (Haiku)**
On file upload, parse DOCX/PDF → title, abstract, authors, affiliations, keywords, funding statement, ethics statement, references. Pre-fill the submission wizard; author confirms. Cuts submission time from ~20 min to ~4.

**A2. Desk screening report (Haiku + deterministic checks)**
Runs automatically on submission. Produces a one-screen editor report:
- Scope fit against the journal's aims & scope statement (0–100 + rationale)
- Completeness: abstract length, required sections, ethics/consent statement, funding disclosure, conflict-of-interest statement, AI-use disclosure
- Reference health: resolve every DOI against Crossref; flag unresolvable references (a strong paper-mill and hallucination signal), flag retracted references via Crossref/Retraction Watch
- Structural: word count, figure/table count vs journal limits, language quality band
Editor sees flags, not a verdict.

**A3. Reviewer matching (embeddings + Sonnet rationale)**
- Embed manuscript abstract + keywords + references into pgvector.
- Embed each reviewer's profile: declared interests, ORCID works, past review subject areas, previously reviewed manuscripts.
- Rank by cosine similarity, then **hard-exclude on COI**: co-authorship within 5 years (from the reference graph and ORCID), same institution (ROR match), same email domain, author-suggested-exclusions.
- Surface: rank, why-this-reviewer sentence, current review load, historical on-time rate, historical accept rate, average turnaround.
- Reviewer availability and load are as valuable as topical fit. Editors pick reviewers who actually respond.

**A4. Integrity signals (Haiku + heuristics) — advisory only**
Tortured-phrase detection, internal-consistency checks across sections, duplicate/overlapping submission detection across all journals in the installation, authorship-change anomalies between versions, and reference-manipulation patterns (excessive self-citation, citation rings within the journal).
**Framing rule:** these are risk *signals* for editorial attention. Never render a percentage that reads like a verdict. Never call it "AI detection." Never auto-flag an author.

**A5. Production assistant (Sonnet) — the biggest time saver**
DOCX/PDF → clean **JATS 1.3 XML** with tagged sections, figures, tables, and structured references converted to CSL-JSON. Then generate HTML galley and a typeset PDF galley from the JATS. Layout editor reviews and corrects in a side-by-side XML/preview editor.
This is where OJS burns the most human hours. If nothing else in the AI layer works, this alone sells the product.

**A6. Multilingual (Sonnet) — the SEA differentiator**
- Khmer ⇄ English translation of title, abstract, and keywords, generating a second-language metadata record so Cambodian-language research becomes discoverable in English-language indexes and vice versa.
- Khmer audio abstract/full-text galley via Azure `km-KH` TTS.
- Journal UI available in Khmer at launch. This is a competitive fact: local journals cannot get their language work indexed today.

**A7. Reviewer assist (Sonnet, opt-in per journal)**
For the reviewer: manuscript summary, methods checklist, structured review-form scaffold. Explicitly **not** a review generator — output is a set of questions and observations the reviewer must answer themselves. Requires reviewer to accept an AI-use disclosure, which is recorded on the review.

**A8. Reader layer (Sonnet + pgvector, published content only)**
Plain-language summary, semantic search across the journal's published corpus, related-articles, and a cite-assistant. No confidentiality constraint here — this content is public. Shares the Alphalib RAG pipeline.

---

## 7. Public site

- Server-rendered (Inertia SSR) — mandatory for Google Scholar and for readers on slow connections.
- Journal homepage, current issue, archive, article landing page, search, announcements, editorial team, submission guidelines, static pages.
- 2–3 clean built-in themes with configurable colors/logo/fonts. **No theme marketplace in v1.**
- Article landing page must include: full metadata, all galley download links, citation export (BibTeX/RIS/CSL-JSON), COUNTER-counted metrics, license badge, DOI, ORCID links, and a versions list.
- Accessibility: WCAG 2.2 AA. Institutions in the EU and increasingly SEA procure on this.
- Khmer web typography must be correct — line-height and font stack for Khmer script, tested on Noto Sans Khmer and Kantumruy Pro. Do not ship a Latin-only type scale.

---

## 8. Build phases

| Phase | Scope | Est. |
|---|---|---|
| **P0 Foundation** | Auth, users, journals, roles/policies, journal settings, multilingual infra, R2 files, activity log, Alpha Scholar Core package skeleton | 2–3 wk |
| **P1 Editorial spine** | Submission wizard, workflow state machine, review rounds + forms + assignments, decisions, queries/discussions, copyediting, production, issue assembly, publish. Email templates + notifications. **Happy path end to end.** | 5–7 wk |
| **P2 Interop** | OAI-PMH, Crossref, DOAJ, Scholar meta, ORCID, ROR, JATS, COUNTER, exports, preservation. **Validate against every external validator.** | 3–4 wk |
| **P3 Migration** | OJS Native XML importer, direct DB reader, URL redirect map, fidelity report CLI | 2–3 wk |
| **P4 Public site** | SSR frontend, themes, search, accessibility, Khmer typography | 2–3 wk |
| **P5 AI layer** | A1 → A8 in order. A5 (production assistant) before A3 if you need a demo that sells. | 4–5 wk |

**Do not start P5 before P2 is validated.** An AI-powered journal that Google Scholar cannot index is unsellable; a plain journal that indexes correctly sells today.

**First customer target:** one real Cambodian university journal, migrated from their existing OJS install, live and indexed. Everything else is secondary to that reference.

---

## 9. Testing requirements

- Workflow state machine: every legal and illegal transition.
- **Anonymity leak tests:** for each review type, assert reviewer identity is absent from every API response an author or another reviewer can reach.
- Permission matrix: every role × every stage × every action.
- OAI-PMH: validate against the OpenAIRE validator in CI.
- Crossref XML: validate against the official XSD in CI.
- JATS output: validate against JATS 1.3 XSD in CI.
- Migration: round-trip an OJS export, assert counts and DOIs match.
- AI: golden-file tests on prompt outputs; assert no AI code path can write to `editorial_decisions`.

---

## 10. Naming & conventions

- Product: **Alpha Journal** (`alpha-journal`), Alphalib brand line.
- PHP namespace: `Alpha\Journal\`, shared core `Corasoft\AlphaScholar\`.
- Tables: singular-context prefix where journal-scoped (`journal_settings`, `submission_files`).
- All money in integer minor units. All timestamps UTC, rendered in journal timezone.
- Language codes: BCP-47 (`en`, `km`, `fr`), stored consistently — do not mix `km-KH` and `km`.

---

## 11. OJS parity checklist

Sections 1–10 define the spine. This section is the remaining surface area needed before a real journal can switch. **Nothing here is optional except where marked.**

### 11.1 Email & notification subsystem — P1, load-bearing
- Per-journal, per-locale editable templates with variable substitution. Minimum set: submission acknowledgement, submission ack (co-authors), editor assignment, review request, review request reminder, review accepted/declined, review overdue, revisions requested, resubmit for review, decline, accept, copyedit request, layout request, proofread request, publication notice, new issue announcement, password reset, user registration, ORCID request.
- Journal Manager UI to edit, reset to default, disable, and preview any template.
- All outbound mail logged against the submission and visible in the audit trail.
- Per-user notification preferences + digest option.
- Bulk email to a role (with unsubscribe compliance).

### 11.2 Scheduled tasks — P1, load-bearing
Laravel scheduler + Horizon:
- Reviewer reminder before due date (configurable N days) and after (escalating).
- Overdue review escalation to assigned editor.
- Unclaimed submission alert (no editor assigned after N days).
- COUNTER usage rollup, nightly.
- DOI deposit retry + status poll.
- Embargo release.
- Session/temp file cleanup.
- Automated stats email to editors (weekly).

**Without automated reminders, review turnaround collapses. This is the most-used feature in OJS. Do not defer it.**

### 11.3 Submission configuration — P1
- Author guidelines (multilingual rich text), submission checklist items author must tick, competing-interest statement, privacy statement.
- Copyright holder, copyright year basis (issue vs article), license URL picker (CC family), self-archiving policy.
- Per-section: name, abbreviation, policy, word limit, `review_not_required` flag, `not_in_toc` flag, assigned section editors, section-specific review form.
- Author-suggested reviewers and author-excluded reviewers (free text + reason) — feeds the COI exclusion in A3.
- Metadata field configuration: which fields (keywords, subjects, coverage, rights, disciplines, agencies, citations) are enabled, and whether required at submission.

### 11.4 User & account management — P1
- Invitation-based enrollment (email invite → role assignment on accept).
- Bulk enroll into a role; CSV import of users.
- Merge duplicate users (preserves submission and review history — required after migration).
- Reviewer profile: interests, reviewing interests, ORCID works, availability toggle, "unavailable until" date.
- Reviewer performance record: assignments completed, average days, on-time rate, editor quality rating (1–5, private).
- User disable/suspend without deletion.

### 11.5 Privacy & compliance — P2
- GDPR/PDPA consent capture at registration with versioned consent text.
- User data export (machine-readable, all personal data across submissions and reviews).
- Account deletion request flow with editorial-record retention policy documented.
- Cookie/analytics disclosure.

### 11.6 Access & embargo — P2
- Open access (default), **delayed open access** with configurable embargo period per journal or per issue.
- Article-level embargo override.
- No subscription/paywall commerce in v1 — if a customer demands it, treat as custom work, not core.

### 11.7 Statistics — P2
- **Editorial dashboard:** submissions received, accepted, declined, acceptance rate, days to first decision, days to accept, days to publish, reviews per submission, reviewer response rate — filterable by section, editor, and date range.
- **Usage dashboard:** article views, galley downloads, by month, by geography, COUNTER R5 compliant with robot filtering.
- Exportable CSV for annual reports and DOAJ/accreditation submissions.

### 11.8 Issue & publication detail — P1/P2
- Issue-level galley (full-issue PDF) and issue-level DOI.
- Issue cover image, description, volume/number/year, `published`/`unpublished` state, future-dated publishing.
- Table of contents ordering (drag), section ordering within issue.
- Dependent files: images and assets referenced inside an HTML/JATS galley must resolve — model as `dependent_files` attached to a galley, not loose submission files.
- Supplementary files / research data with their own metadata and optional DOIs.
- Article "Publication Facts Label" equivalent (review type, reviewer count, acceptance rate, funder disclosure) — OJS 3.5 ships this and DOAJ likes it. Low effort, good optics.

### 11.9 Extension points — P2, architectural
No plugin gallery in v1, but **do not ship without extension seams** or every client becomes a fork:
- Domain events on every workflow transition, publish, deposit, and AI run.
- A registered hook contract for: metadata format providers, deposit targets, galley renderers, auth providers, payment providers.
- Theme = a publishable Blade/React layout set + token file, loadable per journal without touching core.

### 11.10 Site administration — P1
- Site settings: title, logo, primary locale, installed locales, redirect-to-single-journal option.
- Journal creation/enable/disable/reorder from the site level.
- System info, queue health, storage usage, DOI deposit failures across all journals.
- Site-wide bulk email, expire sessions, clear caches.

### 11.11 UI localization — P1
- Full UI translation files for `en` and `km` at launch; `fr`, `vi`, `th`, `lo` as stretch.
- Translation management screen so a Journal Manager can override any string for their journal without a deploy.
- **Khmer translation is a Corasoft differentiator — no complete Khmer OJS locale exists. Do this properly and it becomes a reason to switch on its own.**

### 11.12 Explicitly out of scope for v1
Monographs (OMP), preprints (OPS), plugin marketplace, theme marketplace, subscription paywalls, PKP Preservation Network membership, custom block manager. Revisit only after the first three paying customers.

---

## 12. Definition of done for v1

A real Cambodian university journal has:
1. Migrated its full back-catalogue from OJS with DOIs and URLs preserved.
2. Run one complete submission → review → accept → production → publish cycle in the new system.
3. Passed the OpenAIRE OAI-PMH validator.
4. Successfully deposited a DOI to Crossref.
5. Had a new article indexed in Google Scholar.
6. Published bilingual Khmer/English metadata on that article.
7. Produced an audit report an accreditation reviewer accepted.

Until all seven are true, the product is not done — regardless of feature count.

---

## 13. UX doctrine — where we deliberately diverge from OJS

**Governing rule:** diverge on the **interface**, never on the **data model or standards**. Galley, issue, section, review round, publication version, and stage must survive intact underneath — Crossref deposit, OAI-PMH, JATS, COUNTER, and the OJS importer all depend on them. Every divergence below is a presentation-layer decision. If a proposed UX change requires altering the underlying entities or their relationships, it is rejected.

Where this section conflicts with "match OJS", **this section wins**.

### 13.1 Submission: one screen, not a five-step wizard
OJS's wizard loses authors at the file-component dropdown ("Article Text" vs "Research Instrument" vs "Transcripts" — authors guess, and guess wrong).

Ours:
- Single scrolling screen with a progress rail, not sequential locked steps.
- Drag files in first. AI (A1) classifies file role and extracts metadata. The author **confirms and corrects**, never types from scratch.
- Author-facing labels: "Your manuscript", "Figures and tables", "Supporting data", "Cover letter". Map to the standard component vocabulary internally.
- Autosave every change as a draft from the first keystroke. Resumable from email.
- Progressive disclosure: required metadata visible, optional metadata behind "Add more details".
- Show the submission checklist inline as you go, not as one intimidating wall at step one.
- **Target: under 5 minutes for a returning author.**

### 13.2 Submission Workspace: one page, no modal stacking
OJS opens modals inside modals with no addressable URL, so an editor cannot link a colleague to a specific review.

Ours: one route per submission, `/journal/{path}/submissions/{id}`, three panes:
- **Left** — vertical timeline: every event, decision, file version, and message in chronological order. This is the audit trail rendered as a UI.
- **Centre** — the current task, contextual to stage and role. An editor in review stage sees reviews and the decision action. A copyeditor sees the file and the discussion.
- **Right** — people (with role and status) and files (with version).

Every panel state is a URL. No modal may open another modal. Drawers and inline expansion only.

### 13.3 One canonical manuscript, not stage buckets
OJS scatters files across Review Files / Revisions / Draft Files / Copyedited / Production Ready. Editors routinely work from the wrong version.

Ours: a submission has **one current manuscript** at all times, plus a version history strip showing who changed it, when, and at which stage. Supporting files are a separate, flat list. Stage association is metadata on the version, not a separate bucket the user navigates.

### 13.4 Action queue, not a submission grid
A table of everything tells an editor nothing about what to do next.

Editor home is a ranked queue:
- Needs your decision (n)
- Reviews overdue (n) — with a one-click nudge
- Awaiting reviewer response (n)
- No editor assigned (n)
- Ready to publish (n)

The full filterable table still exists, one click away. It is not the landing screen.

### 13.5 Decisions show their email before sending
OJS sends templated mail the editor never saw.

Ours: recording a decision opens a drawer showing the exact rendered email — recipients, subject, body, attachments — fully editable, with a "record decision without sending" option. Sent copy is stored on the timeline.

### 13.6 Settings: searchable, not thirty tabs
OJS buries configuration across Journal / Website / Workflow / Distribution with dozens of tabs.

Ours: one settings surface with a persistent search box and Cmd-K command palette that jumps to any setting by name or by what it does ("reviewer reminder", "who can publish", "DOI prefix"). Group by task, not by subsystem. Every setting has one-line plain-language help and shows its current effective value.

### 13.7 Reviewer flow is mobile-first
In Cambodia and the wider region, reviewers work from phones. OJS's reviewer flow is desktop-only in practice.

Ours:
- **Accept or decline in one tap from the invitation email via a signed, expiring link — no login required.** This single change moves reviewer response rates more than any AI feature in §6.
- Review form is a mobile-native, autosaving, resumable form. Never lose a partially written review.
- File preview in-browser without download.
- Deadline, remaining days, and "request extension" always visible.
- After accepting, a calendar (.ics) invite for the due date.

### 13.8 Plain language in the UI
Keep publishing standard terms in exports, the API, and OAI-PMH. Change what humans read.

| OJS term | Our UI label |
|---|---|
| Participants | People |
| Query | Discussion |
| Component | File type |
| Galley | Published file (PDF / HTML / XML) |
| Stage | Step |
| Submission Library | Shared documents |
| Production Ready Files | Final files |

Khmer labels must be reviewed by a working academic editor, not machine-translated. Publishing vocabulary in Khmer is unsettled — pick terms and keep a glossary in the repo.

### 13.9 Cross-cutting interaction requirements
- Bulk actions on any list (assign editor, send reminder, export).
- Keyboard shortcuts and Cmd-K palette throughout.
- Every list filterable and every filter state in the URL.
- Optimistic UI with clear failure recovery — connections in the region are unreliable.
- No action that sends email is irreversible without a confirmation showing the recipients.
- Empty states explain the next action; never show a blank grid.

### 13.10 Validation before build
Before writing P1 UI, run a 45-minute session with **two real journal editors and two real authors** currently using OJS. Watch them submit and watch them make a decision. Record where they hesitate. Those hesitation points are the spec.

Repeat after P1 with the working build. Do not skip this — the entire product thesis is "OJS but usable," and that claim is only true if it is tested against people who have suffered OJS.
