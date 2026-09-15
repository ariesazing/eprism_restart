# ePrism Research Workflow

A Laravel 12 web app for managing a school's research proposal/completed-research workflow:
researchers draft and submit manuscripts, reviewers score them, admins configure submission
windows, document templates, and organizational units. It generates PDFs (the manuscript itself,
plus RAPM review-summary/routing-slip documents), and has a real-time comment/discussion layer
over Laravel Reverb.

See [CLAUDE.md](CLAUDE.md) for the full architecture reference (roles, submission lifecycle, the
three editor engines, PDF pipelines, real-time layer). This file covers setup, environment
configuration, and — since it's the newest and most operationally involved piece — the
ONLYOFFICE-based complete-manuscript workflow in detail.

## Requirements

- PHP 8.2+ with the extensions Laravel 12 needs (`composer install` will tell you if one's
  missing), Composer 2
- Node 18+ and npm
- MySQL (or another Laravel-supported database)
- Python 3.10+ with the packages in `scripts/requirements.txt`, for the manuscript workflow's
  structural validation, proposal-to-completed migration, PDF merging, and PDF encryption steps
- A self-hosted [ONLYOFFICE Document Server](https://github.com/ONLYOFFICE/DocumentServer)
  instance, for both the legacy per-chapter editor and the complete-manuscript editor
- `qpdf`, for normalizing Document Server's own PDF output so FPDI can read it (only needed by
  the legacy per-chapter manuscript pipeline — see `OnlyOfficeService::normalizePdfForFpdi()`)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Create a Python virtual environment for the manuscript worker and install its dependencies:

```bash
python -m venv .venv
# Windows: .venv\Scripts\pip install -r scripts/requirements.txt
# macOS/Linux: .venv/bin/pip install -r scripts/requirements.txt
```

`config/manuscripts.php` resolves the interpreter automatically: it uses `MANUSCRIPT_PYTHON` if
set, otherwise `.venv/Scripts/python.exe` (Windows) if that file exists, otherwise falls back to
whatever `python3` resolves to on `PATH`. Set `MANUSCRIPT_PYTHON` explicitly if none of that
finds the right interpreter — same reasoning as `QPDF_BINARY` below: a value that only takes
effect in *new* processes is easy to set correctly and still have an already-running dev
server/terminal not see it.

Run migrations and seed:

```bash
php artisan migrate
php artisan storage:link
```

`php artisan migrate:status` before any migration against a database that isn't a disposable
local one — the Docker entrypoint (`docker/entrypoint.sh`) runs `migrate --force`
*automatically* on every container start, with no separate confirmation step, so a bad migration
reaches whatever database that container is pointed at the moment it deploys.

## Running locally

```bash
composer dev
```

This runs the app server, Vite dev server, Reverb, and a queue worker (`queue:listen`)
concurrently — see `composer.json`'s `dev` script. It does **not** run the scheduler
(`schedule:work`), which matters specifically for the manuscript workflow's stuck-session
recovery (`manuscripts:expire-save-waits`, every minute — see below); run
`php artisan schedule:work` in a separate terminal alongside `composer dev` if you need that
running locally, or trigger a single tick manually with `php artisan schedule:run`.

## Environment variables

Beyond the standard Laravel ones, `.env.example` documents these — the manuscript-workflow-
specific ones are new and worth calling out explicitly:

| Variable | Purpose |
|---|---|
| `QUEUE_CONNECTION` | **Must not be `sync`** for the manuscript workflow to behave as designed. Every manuscript-processing job (`ProcessManuscriptVersion`, `EncryptApprovedManuscript`, `ComposeManuscriptPreview`) is dispatched expecting to run *off* the request that triggered it — with `sync`, they instead run inline: a Document Server save callback would block on the full validate/convert/merge pipeline before Document Server ever gets its response, and a preview request would resynchronize itself back into the exact "runs in the web request" problem it was built to avoid. `.env.example` defaults to `database`; production uses `database` via `supervisord`'s `queue:work` processes. |
| `DB_QUEUE_RETRY_AFTER` / `REDIS_QUEUE_RETRY_AFTER` | Must stay comfortably above every manuscript job's own `$timeout` (`ProcessManuscriptVersion`/`EncryptApprovedManuscript`: 600s, `ComposeManuscriptPreview`: 300s) — otherwise the queue driver can decide a still-running job "died" and dispatch a second worker onto the same job while the first is still going. Defaults to 900s; if you override it in a specific environment, re-check it against these jobs' timeouts. |
| `ONLYOFFICE_ENABLED`, `ONLYOFFICE_URL`, `ONLYOFFICE_JWT_SECRET` | Standard Document Server connection. New submissions only get `EditorEngine::ONLYOFFICE` (per-chapter drafting) when `ONLYOFFICE_ENABLED` is true at creation time (see `ResearchSubmissionController::store()`) — with it off, new submissions fall back to the `canvas_editor` engine, and `onlyoffice`/`onlyoffice_manuscript` submissions already in the database keep working (each engine's own pipeline doesn't depend on this flag once a submission already has it), but nothing *new* can open the ONLYOFFICE editor while it's off. |
| `ONLYOFFICE_CALLBACK_BASE_URL` | Only needed when Document Server can't reach this app at its own `APP_URL` — most commonly DS running in a container where `localhost` means the container itself, not the host. |
| `QPDF_BINARY` | Set to qpdf's full executable path if it isn't resolvable via `PATH` for whatever process actually runs this app. A Windows PATH change only takes effect in processes started *after* it was made — a long-running dev server/terminal keeps whatever PATH it inherited at startup — which has caused real, confusing failures in this project before. Leave empty to rely on PATH (what the production image's apt-installed qpdf already lands on). |
| `MANUSCRIPT_PYTHON` | Full path to the Python interpreter with `scripts/requirements.txt` installed — see Setup above for the same "PATH may not be enough" reasoning. |

## Testing

```bash
composer test                                          # full PHP suite (clears config cache first)
php artisan test --filter=ManuscriptWorkflowTest        # one class
vendor/bin/pint --dirty                                 # code style on changed files only
.venv/Scripts/python.exe -m unittest discover -s tests/Python -v   # Python worker's own tests
npm run build                                            # frontend build
```

Don't run two PHP suites concurrently against the same working tree: several tests use
`Storage::fake('local')` against shared fixture paths, and parallel runs can delete one
another's files mid-test.

PHP feature tests for the manuscript workflow mock `ManuscriptProcessor` (the Python bridge) and
`OnlyOfficeService` — they verify the *state machine* (locking, idempotency, staleness guards,
error handling) thoroughly, but passing them is not evidence that a real Document Server round
trip or a real Python conversion actually produces a correct document. Live-verify those
separately against a real Document Server and a real `.venv` before trusting a change to either
integration.

## Chapter-by-chapter drafting (the current default)

New submissions (when `ONLYOFFICE_ENABLED=true`) use `EditorEngine::ONLYOFFICE`: each `rich_text`
chapter is its own isolated `.docx`, edited entirely inside ONLYOFFICE
(`resources/views/researcher/submissions/partials/chapter-panels.blade.php`, mounted via
`resources/js/document-editor/onlyoffice.js`) — the researcher only ever sees that one chapter,
never the admin's front-matter template or a `${...}` placeholder token. `table`-type sections
(cost estimates, timetables) stay plain HTML form inputs regardless of engine.

- **Editing/saving**: chapter tabs sit to the left of the panel they switch between
  (`initChapterWizard` in `submission-editor.js`); only the currently-selected chapter's
  ONLYOFFICE editor is ever mounted (`OnlyOfficeDocumentController::config()`, which seeds a
  blank starter `.docx` the first time a chapter is opened — see
  `SubmissionSectionService::ensureOnlyOfficeDocument()`). Saving is automatic through
  ONLYOFFICE's own save/force-save callback loop (`OnlyOfficeDocumentController::callback()`),
  which also refreshes a sanitized `content_html` mirror (via Document Server's own docx→HTML
  conversion) so readiness checks, SRAM scoring, and comments keep working exactly as they do
  for a `canvas_editor` chapter.
- **Authoring the template**: the admin writes each chapter's heading directly in the
  `.docx` template, with that chapter's own `${<section_key>}` token as its own paragraph
  right where the chapter's content should go (see the "Chapters (rich text)" panel in
  `admin/document-templates/edit.blade.php`'s placeholder sidebar) — e.g. a
  "CHAPTER 1 / CONTEXT AND RATIONALE" heading followed by a paragraph containing exactly
  `${context_and_rationale}`. The admin controls chapter order, titles, and page layout
  entirely through this document; the researcher's own chapter document never repeats the
  title.
- **Document generation**: `SubmissionDataBuilder` fills the admin template's ordinary scalar/
  table placeholders (title, proponents, structured chapters) via `DocxTemplateFiller`, which
  deliberately leaves every chapter's own `${<key>}` token untouched; `ManuscriptProcessor`'s
  `assemble_chapters` operation (`scripts/manuscript.py`, via `docxcompose`) then splices each
  rich_text chapter's own `.docx` in at that exact paragraph — importing its images, styles,
  and numbering into the template's own package, restarting numbered lists per chapter, and
  reusing (by name) any style the chapter and template already share — before removing the
  placeholder paragraph itself. `SubmissionDocxComposer::composeManuscript()` converts that one
  assembled document to PDF via ONLYOFFICE's own `ConvertService.ashx` (a real docx→PDF
  conversion, so images/tables/equations/special characters survive natively — not an
  HTML/dompdf re-render) in a single pass — front matter and every chapter are native pages of
  the same PDF, so headers/footers/margins are the admin template's own layout, nothing needs
  restamping. `SubmissionDocxPdfMerger` then appends that one manuscript PDF, followed by
  attachments stamped with a blank-body letterhead reference (`DocxLetterheadExtractor`) — the
  only remaining separately-produced source, since an uploaded attachment PDF has no header/
  footer of its own. See `SubmissionSnapshotService::composePreviewViaOnlyOffice()`.
  A chapter with real content but no matching placeholder (or a duplicate, or one placed inline
  or inside a table cell) fails generation loudly, naming the chapter — never silently appended
  elsewhere or left as a literal unresolved `${...}` in the manuscript. A chapter with no
  content yet (optional, or not written) has its placeholder quietly removed instead, leaving
  no unresolved token. Known limitation (inherited from `docxcompose`, not fixed): a chapter
  whose own trailing section is landscape does not carry that orientation into the assembled
  manuscript — the content survives, only the page orientation doesn't.

Existing `canvas_editor` submissions are unaffected (permanent for submissions that already have
chapters in that shape) — see `App\Enums\EditorEngine`'s own doc comment for all three engines.

## The complete-manuscript workflow

`EditorEngine::ONLYOFFICE_MANUSCRIPT` is no longer assigned to new submissions (superseded by
the per-chapter drafting above) but is still fully supported for whichever submissions already
have it: the whole manuscript — front matter and every chapter — is one continuously-edited
`.docx`, rather than a separate document per chapter. See `App\Enums\EditorEngine` and
`ResearchSubmission::usesManuscript()`/`usesOnlyOffice()`.

### How a manuscript is authored

1. An admin authors a template `.docx` per `research_type`/`classification` combination (the
   same `SubmissionDocumentTemplate` model the other two engines use, via `docx_path`).
2. Where the admin wants a specific chapter's content to end up, they can type a literal
   `${section:<key>}` token (the section keys come from `SubmissionTemplateRegistry`, e.g.
   `${section:context_and_rationale}`) as its own paragraph — `scripts/manuscript.py`'s `seed()`
   replaces it with a locked Word content control (heading + editable placeholder). This anchor
   is **optional**: any section without one gets appended at the end of the document instead, so
   an admin who never learns this convention still gets a working template, just with every
   chapter in template-definition order at the bottom rather than wherever they'd have chosen to
   place it.
3. Everything else in the template becomes a second kind of locked content control
   (`eprism-metadata:<n>`) — read-only to the researcher, so front matter can't be accidentally
   edited away.
4. `DocxTemplateFiller` fills `${key}` scalar placeholders (title, proponents, etc.) the same way
   it does for the other two engines, with `optionalBlocks: true` — a whole-document manuscript
   template may reasonably have no each-block placeholders in it at all.

### The editing/save state machine

`ResearchSubmission.manuscript` (a JSON column) is the live working/session/processing state;
`manuscript_versions` holds every frozen candidate — immutable once created (see
`ManuscriptVersion`'s own model guards), with parent links, template/docx checksums, metadata and
attachment snapshots, validation results, and (once approved) final-PDF fields.

- **Opening the editor** (`ManuscriptController::config()`) sets `session_open=true` and returns
  Document Server's editor config. A save (status 6, mid-session force-save, or status 2/4,
  session actually closing) downloads the saved `.docx` and verifies it (signed URL, JWT payload,
  size cap, real DOCX structure) before replacing the working copy — all under a row lock, so
  callback arrival order is what decides outcome, not arrival timing.
- **Submitting** (`ManuscriptService::requestSubmission()`) sets state to `waiting_for_save`; if
  no editor session is open, it freezes immediately from the current working copy. If one is
  open, freezing happens once its close callback lands.
- **Stuck-session recovery** (`ManuscriptService::recoverStuckSessions()`, scheduled every
  minute as `manuscripts:expire-save-waits`): if `config()` set `session_open=true` but the
  editor never actually finished loading (script/network failure, tab closed early), no close
  callback ever arrives to clear it. Recovery asks Document Server directly (via
  `CommandService.ashx`'s `info`/`forcesave` commands, not a guess) whether it's still tracking
  that session — confirmed gone: freeze immediately from the working copy already on disk,
  fully self-healing; still open: nudge a force-save and keep waiting; unreachable: leave the
  flag alone and try again next tick. Only gives up (a clear, retryable error, `session_open`
  left untouched since it's still unconfirmed) after `manuscripts.save_timeout_minutes` (10).
- **Validation and PDF preparation** (`ProcessManuscriptVersion`, queued) re-verifies checksums,
  runs the Python validator, and — only if valid — converts to PDF, merges attachments, and
  publishes a `ResearchSnapshot`, all inside one row-locked, idempotency-guarded transaction (a
  second run against an already-`ready` version is a safe no-op).
- **Draft previews** (`ManuscriptPreviewService`/`ComposeManuscriptPreview`, queued) are
  disposable and keyed by a hash of the current working docx + attachment set — requesting one
  when an identical preview is already queued or ready is a no-op, and a stale job finishing
  late after a newer request superseded it will not overwrite the newer result.
- **Approval** dispatches `EncryptApprovedManuscript` (queued), which encrypts the *already-
  approved, immutable* review PDF with pikepdf (a random owner password, empty user password —
  this restricts editing/copying for a PDF *reader* that respects those permissions, it does not
  restrict who can *open* it; don't describe it as access-controlled). A failure records
  `final_pdf_error` on the version (visible in the researcher's own document-versions list, with
  a retry button) rather than leaving "being prepared" showing forever — safe to retry any
  number of times, since the job only ever reads the immutable review PDF, never anything
  mutable.
- **Repository links**: a proposal's `classification` is overwritten to `completed` in place on
  promotion, so "the submission's current manuscript" stops meaning "the approved proposal" the
  moment that happens. `ResearchSubmission::approvedManuscriptVersion($classification)` (manuscript
  engine) and `snapshotApprovedAsOf($timestamp)` (legacy engines) resolve each repository card to
  the document that specific stage was actually approved with.

### Known limitations (not fixed, worth knowing)

- The Python validator's style/formatting checks (`scripts/manuscript.py`'s `validate()`)
  compare against the template's own `docDefaults`/named styles and flag direct font/size/
  spacing overrides — this is a structural, XML-level check, not a rendered visual diff. It will
  not catch every way a document could look wrong; a custom style not present in the template
  produces a warning, not a hard failure, and needs a human to actually look at the result.
- PDF permissions on the final encrypted PDF are real (`pikepdf.Permissions`) but the document
  has no user password, so anyone with the file can open it — see the approval bullet above.
- This README and the manuscript-specific tests were written/audited primarily against mocked
  Document Server and Python responses (matching this project's own PHPUnit conventions). A real
  Document Server round trip — concurrent tabs, reconnects, delayed/duplicate/reordered
  callbacks under actual network conditions — has not been exercised live as part of this pass;
  treat the state-machine guarantees above as tested, and the live integration as something to
  verify against your own running Document Server before depending on it in production.
