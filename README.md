# Alpha Journal

An open-access journal publishing platform — submission, peer review, editorial decisions,
production and publication, with the interoperability a journal needs to be found: OAI-PMH,
Crossref and DataCite deposit, DOAJ upload, Google Scholar metadata, and full-text search.

Built for BELTEI International University, Cambodia, and designed to be run by any journal.
It is bilingual (English / Khmer) throughout.

**Stack:** PHP 8.2+ · Laravel 12 · Inertia 2 · React 19 · TypeScript · Tailwind 4 · PostgreSQL

The product specification lives in [`ALPHA-JOURNAL-CLAUDE.md`](ALPHA-JOURNAL-CLAUDE.md), and the
user-facing manual is served by the app itself at `/handbook`.

---

## Requirements

| | |
|---|---|
| PHP | 8.2 or newer |
| Composer | 2.x |
| Node | 20 or newer |
| **PostgreSQL** | 14 or newer — **not optional**, see below |

## Setup

```bash
git clone https://github.com/corasolution/alphalib-repository.git
cd alphalib-repository

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Create the database and point `.env` at it:

```bash
createdb alpha_journal
```

```dotenv
DB_CONNECTION=pgsql
DB_DATABASE=alpha_journal
DB_USERNAME=postgres
DB_PASSWORD=

# .env.example ships with Redis for both. If you do not have a Redis server
# running, change them or the app fails on the first request.
QUEUE_CONNECTION=database
CACHE_STORE=file
```

Then:

```bash
php artisan migrate
php artisan db:seed      # creates a site, a demo journal, and an admin account
npm run build            # required — public/build is not in the repository
php artisan serve
```

The seeder prints the accounts it created. The site administrator is
`admin@example.com` with the password `password`.

> **Change that before the site is reachable by anyone else.** It is a well-known
> credential with full control of the installation, including the screen holding your
> Crossref deposit credentials.

## Four things that catch people out

**PostgreSQL is required, not preferred.** Article search uses a generated `tsvector` column
with a GIN index, and [that migration](database/migrations/2026_09_10_120000_add_search_vector_to_publications_table.php)
deliberately skips itself on other drivers. On MySQL the app installs cleanly, runs, and search
silently returns nothing. SQLite is used only by the test suite.

**`npm run build` is not optional.** `public/build` is gitignored, so without it every page fails
with *"Unable to locate file in Vite manifest"*.

**The Redis defaults.** `.env.example` sets `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis`.
Either run Redis or change them, as above.

**Deposits default to the test endpoints.** Crossref and DataCite both run sandboxes that accept a
deposit, answer `submitted`, and register nothing. A new installation starts pointed at those on
purpose. Admin → Indexing has the live/test switch; move it only when you intend real DOI
registrations.

## Running the tests

```bash
php artisan test
```

645 feature and unit tests, running against in-memory SQLite — no database setup needed.

Front-end checks:

```bash
npx tsc --noEmit     # types
npm run lint
npm run format
```

## Orientation

| Path | |
|---|---|
| `app/Workflow/SubmissionWorkflow.php` | The editorial state machine. Every stage transition and its guards. Start here. |
| `app/Enums/` | Stages, decisions, roles, review types — the domain vocabulary |
| `app/Models/Scopes/JournalScope.php` | Per-journal data isolation. Denies by default when no journal is in context |
| `app/Crossref/`, `app/DataCite/`, `app/Doaj/` | Deposit and indexing |
| `app/Search/ArticleSearch.php` | Reader-facing search |
| `packages/alpha-scholar-core/` | Shared file storage and persistent-identifier code (a path package, committed here) |
| `resources/js/pages/` | Every screen. `public/` is reader-facing, the rest is staff |
| `routes/journal.php` | The editorial routes, scoped by journal |

### A note on journal scoping

`JournalScope` constrains queries to the journal in the current request and **denies by default**
when there is no journal in context. Anything legitimately cross-journal — site-wide search, the
admin screens — must opt out explicitly with `withoutGlobalScope(JournalScope::class)`.

The scope bypasses entirely under `artisan test`, so a green suite does not prove a cross-journal
query works. Tests that need the real behaviour set `config(['journal.scope_console_bypass' => false])`;
`ArticleSearchTest` has an example and explains why.

## License

**Unsettled — do not distribute this code until it is.** `composer.json` currently declares `MIT`,
which is the Laravel starter kit's default and has never been a deliberate choice for this project.
There is no `LICENSE` file. The specification states the intent to license a hosted tier separately,
which an MIT declaration would undercut.

Decide this before the repository is shared outside the team, and add a `LICENSE` file to match.
