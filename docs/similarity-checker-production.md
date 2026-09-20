# Similarity checker — full production rollout guide (Dokploy)

The complete process, start to finish: before you push, deploying, adding SearXNG, verifying,
going live, monitoring, and rolling back. Tick the boxes as you go.

## What this release ships

| Feature | What users see |
|---|---|
| **Similarity Check** | A card below the draft form on each research page, and a report page that highlights passages found elsewhere on the web and lists their sources. Advisory only — it never blocks a submission. |
| **Submit confirmation** | Clicking **Submit** / **Resubmit** now opens an "are you sure?" step summarising what's being sent, instead of submitting on one click. |
| **Lighter research page** | The research page no longer downloads the 697 KB chapter-editor script it never used (866 KB → 170 KB of JavaScript). The chapters page is unchanged. |

How the similarity check works, in one paragraph: it takes about 24 spread-out phrases from the
chapters, searches each as an exact phrase on a **self-hosted SearXNG** (a search server you run —
no API key, no per-query fee), downloads the pages that come up most, and compares them word-for-word
with the chapters. It runs as a **background job** on the app's existing queue workers.

**It only detects copied wording** found by the web's search engines. It does not detect reworded
text, paywalled or login-only pages (most of ResearchGate, publisher sites), PDFs, or Google Scholar,
and it does **not** compare against other ePrism submissions. A low score means "nothing found",
never "original".

**Safe by design:** the app can be deployed *before* SearXNG exists. Until `SEARXNG_URL` is set, a
check finishes in seconds and the report says *"no SearXNG server is configured"* — nothing spins or
breaks. That's why SearXNG is added *after* the app in this guide.

> Total time: about 45 minutes, most of it Part 3–5. A real check takes 30–90 seconds.

---

## Part 0 — Before you push (on your computer)

- [ ] **Run the tests.**
  ```bash
  php artisan test
  ```
  Expected: **everything passes** (335 tests at the time of writing). Anything failing is worth
  stopping for. (`ManuscriptWorkflowTest` and `SubmissionDocxManuscriptTest` are timing-sensitive and
  have failed once in a full run before, passing on re-run — re-run them alone before worrying.)

- [ ] **Decide what to commit.** This change is:
  ```
  Modified:  .env.example, config/services.php, routes/web.php,
             app/Http/Controllers/ResearchSubmissionController.php, app/Models/ResearchSubmission.php,
             resources/views/researcher/submissions/show.blade.php, .../manuscript-show.blade.php
  New:       app/Similarity/ (whole folder), app/Http/Controllers/SimilarityCheckController.php,
             app/Jobs/RunSimilarityCheck.php, app/Models/SimilarityCheck.php, SimilarityMatch.php,
             config/similarity.php, database/migrations/2026_09_20_000000_create_similarity_checks_tables.php,
             resources/views/researcher/submissions/similarity.blade.php,
             .../partials/similarity-panel.blade.php, .../partials/confirm-submit-modal.blade.php,
             docker/searxng/settings.yml, docker-compose.searxng.yml, docs/similarity-checker-production.md,
             tests/Unit/Similarity/, tests/Feature/Similarity*Test.php, SubmissionConfirmationModalTest.php

  Also in this release (cleanup — safe, all covered by the passing tests):
  Modified:  tests/Feature/SubmissionDocumentTemplateTest.php (two stale tests updated),
             package.json + package-lock.json (removed the unused @tailwindcss/vite),
             resources/js/submission-editor.js (comment only), app/Services/PdfFontFamilyResolver.php
             (comment only), app/Models/Review.php, app/SubmissionTemplates/SubmissionTemplate.php
             (unused methods removed)
  Deleted:   resources/views/admin/document-templates/partials/auto-format-fields.blade.php,
             resources/views/components/responsive-nav-link.blade.php (both unused)
  ```
  Commit the deletions too (`git add -A` on those paths), or they'll reappear on the server.
  - **Never commit `.env`** (it holds your secrets; it's git-ignored — keep it that way).
  - `.agents/skills/prompt-master/` and `.claude/skills/prompt-master/` show as untracked but are not
    part of this change — leave them out unless you want them.
  - `docker-compose.searxng.yml` is for **local development only**. It's excluded from the production
    image by `.dockerignore`; production runs SearXNG as a Dokploy service instead (Part 3).

- [ ] **Back up the production database.** The migration only *adds* two tables, so this is precaution,
  but take a Dokploy database backup (or snapshot) before deploying anything.

- [ ] **Pick a quiet moment.** A deploy restarts the app container and its queue workers. Avoid a time
  when someone is mid-submission or a manuscript is being generated.

---

## Part 1 — Push and deploy the app

- [ ] Commit and push as you normally do.
- [ ] In Dokploy, let the app deploy (or trigger it) and wait for the build to finish. This happens
  automatically — you don't do it by hand:

  | Automatic step | Where it comes from |
  |---|---|
  | Frontend assets rebuilt (the report page uses new CSS) | `Dockerfile` — `npm run build` stage |
  | The two new tables are created | `docker/entrypoint.sh` — `php artisan migrate --force` |
  | Config/routes/views re-cached | `docker/entrypoint.sh` — `optimize:clear` + `optimize` |
  | 2 queue workers run the checks | `docker/supervisord.conf` — `[program:queue]`, `numprocs=2` |

---

## Part 2 — Verify the app deployed (SearXNG not needed yet)

- [ ] Open the app's **Logs**. Look for:
  - `2026_09_20_000000_create_similarity_checks_tables … DONE` (first deploy of this change only)
  - `success: queue_00 entered RUNNING state` and `queue_01`

- [ ] Open the app's **Terminal** and run:
  ```bash
  php artisan migrate:status | grep similarity
  ```
  Expected: `2026_09_20_000000_create_similarity_checks_tables … Ran`.

- [ ] Confirm production settings (they should already be right — this just verifies):
  ```bash
  php artisan tinker --execute="echo config('queue.default'), ' | retry_after=', config('queue.connections.database.retry_after');"
  ```
  Expected: `database | retry_after=900`.
  - It must **not** be `sync` — that would run an up-to-8-minute check inside the web request.
  - `retry_after` must be **above 720**, the check job's time limit.

- [ ] In the browser, open a research draft (any account). You should see the **Similarity Check** card
  below the draft form showing **"The web · Not set up"**. That's correct for now.
  *(If the card is missing, the deploy hasn't finished or the browser cached the old page — hard-refresh.)*

- [ ] **Regression check** — these pages were touched, so glance at each:
  - [ ] Dashboard loads
  - [ ] "My submissions" list loads
  - [ ] A draft's page loads, and **Save** still works
  - [ ] The chapter editor opens and autosaves
  - [ ] **View Manuscript** opens
  - [ ] Clicking **Submit** on a *ready* draft opens the new confirmation step — press **Cancel** (don't submit)
  - [ ] Clicking **Submit** on a draft that is *not* ready still shows the existing "isn't ready yet" message
  - [ ] A reviewer and an admin account both still open their pages normally

---

## Part 3 — Add SearXNG as its own Dokploy service

Set it up the same way your LanguageTool service is (a separate service on the same network as the
app, reached by its internal hostname). *Menu names below are from memory of Dokploy's UI and may
differ slightly in your version — the settings themselves are what matter.*

- [ ] **Create service → Application → Docker image:** `searxng/searxng:latest`
  (after it works, pin a specific version tag — see Part 9).

- [ ] **Environment** — add:
  ```
  SEARXNG_SECRET=<a long random string, e.g. the output of: openssl rand -hex 32>
  ```
  SearXNG refuses to start with a default secret.

- [ ] **File mount** (Advanced → Mounts → File Mount) — mount a file at `/etc/searxng/settings.yml`.
  Its contents are the same as `docker/searxng/settings.yml` in the repo. *Shortcut:* your app container
  already has that file — in the **app's** Terminal, run `cat docker/searxng/settings.yml` and copy the
  output. The essential content is:
  ```yaml
  use_default_settings: true

  general:
    instance_name: "ePrism SearXNG"

  server:
    secret_key: "replace-me-via-SEARXNG_SECRET-env"
    limiter: false
    image_proxy: false

  search:
    safe_search: 0
    formats:
      - html
      - json

  outgoing:
    request_timeout: 6.0
    max_request_timeout: 10.0
  ```
  **The `json` line is essential.** SearXNG has JSON output *off* by default and answers `403` without it.

- [ ] **Do NOT add a domain and do NOT publish a port.** This instance has no login and no rate limit;
  it must only be reachable from inside the Dokploy network (it listens on port **8080** internally).

- [ ] **Deploy** it. In its Logs you should see `Started worker-1`. (A couple of
  `ahmia` / `torch` "can't register engine" errors are normal and harmless.)

- [ ] Write down its **internal hostname** — Dokploy generates it (like `eprism-languagetool-zk97s7`);
  it's the service's app name on its General tab.

---

## Part 4 — Connect the app to it

- [ ] In the **app's** Environment, add (using the hostname from Part 3):
  ```
  SEARXNG_URL=http://<searxng-internal-hostname>:8080
  ```
  Optional: `SEARXNG_LANGUAGE=all` — already the default; it searches every language, which suits
  manuscripts that mix English and Filipino.

- [ ] **Redeploy the app.** Required — `entrypoint.sh` caches config at container start, so the new
  variable isn't seen until the container restarts.

- [ ] In the app's **Terminal**, confirm the value loaded:
  ```bash
  php artisan tinker --execute="echo config('services.searxng.url');"
  ```

- [ ] **Test the connection from inside the app container** (this is the real network path the checker uses):
  ```bash
  php artisan tinker --execute="
  \$r = Illuminate\Support\Facades\Http::timeout(30)->get(config('services.searxng.url').'/search', ['q' => '\"the quick brown fox jumps over the lazy dog\"', 'format' => 'json']);
  echo 'http ', \$r->status(), ' | results: ', count(\$r->json('results') ?? []), PHP_EOL;
  "
  ```

  | Output | Meaning | Fix |
  |---|---|---|
  | `http 200 \| results: 20` (any number above 0) | Working. | Continue. |
  | `http 200 \| results: 0` | SearXNG runs, but its search engines return nothing — most likely your server's IP is being blocked. | See *If Google/Bing block your server*, Part 8. |
  | `http 403` | JSON output isn't enabled. | Re-check the file mount (`json` under `search.formats`); redeploy SearXNG. |
  | Connection refused / could not resolve host | Wrong hostname or port, or the two services aren't on the same network. | Re-check the hostname, port `8080`, and that both are in the same Dokploy project/network. |

- [ ] Reload a research page: the card should now read **"The web · Ready"**.

---

## Part 5 — Smoke test with a real check

Use a **test researcher account**, not a real researcher's draft.

- [ ] Create a draft. In one chapter, paste **both** of these (the chapter needs at least 50 words):
  - two or three sentences you know are on the public web (e.g. copied from a Wikipedia article), and
  - a few sentences you write yourself.
- [ ] Click **Run similarity check**. You land on a progress page that updates itself. **Expect
  30–90 seconds.**
- [ ] **A healthy result:**
  - the copied text is highlighted and a source like `en.wikipedia.org` appears in the side panel;
  - your own sentences are **not** highlighted;
  - there is **no** "Partial results" box;
  - clicking a source in the panel focuses its highlights, and clicking a highlight selects its source.
- [ ] Back on the research page, the card shows the score and a **View report** button.
- [ ] Log in as a **different researcher** and confirm they can't open that report (paste the report's
  URL — expect a "Forbidden" page). Reports are private to the owner.
- [ ] Try **Run again** twice quickly. The second should go to the running check rather than starting
  another (one check at a time per research).

**If the progress page stays on "Checking your chapters…":**
- After 20 seconds it shows *"This check hasn't started yet"* → the queue workers aren't picking jobs
  up. Check the app Logs for `queue_00` / `queue_01`; restart the app container if they're missing.
- A check that's never picked up fails by itself after 5 minutes with a message saying so.

---

## Part 6 — Go live

- [ ] Delete the test drafts/accounts you made in Part 5, if you don't want to keep them.
- [ ] Tell researchers. Suggested wording:

  > **New: Similarity Check.** On your research page, click *Run similarity check* to see which passages
  > of your chapters also appear on the web, and where. It's a self-check to help you spot missing
  > citations — it doesn't block your submission. Quotations and common phrases will match too, and a
  > low score doesn't guarantee originality, so treat it as a prompt to look closer. Short phrases from
  > your chapters are searched through the school's own search server; nothing is published.
  >
  > Also new: **Submit** now asks you to confirm before sending your research for review.

---

## Part 7 — Day-to-day monitoring

All commands run in the **app's Terminal**.

- **Recent checks and what went wrong with them:**
  ```bash
  php artisan tinker --execute="foreach (App\Models\SimilarityCheck::latest('id')->take(10)->get() as \$c) { echo \$c->id, ' ', \$c->status, ' ', \$c->score, '% ', json_encode(\$c->warnings), ' ', \$c->error, PHP_EOL; }"
  ```
- **How many checks succeeded/failed this week:**
  ```bash
  php artisan tinker --execute="echo json_encode(App\Models\SimilarityCheck::where('created_at', '>', now()->subDays(7))->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'));"
  ```
- **Is the queue backing up?** (should be near 0; steadily growing means workers are stuck)
  ```bash
  php artisan tinker --execute="echo DB::table('jobs')->count();"
  ```
- **Application errors:** `tail -n 100 storage/logs/laravel.log` (production logs at `warning` level
  and above; an unexpected failure inside a check is logged there with its stack trace).

**What to watch for:** repeated *"probably blocking this server"* warnings on reports (Part 8), and
failed checks with the *"never started"* message (the workers).

---

## Part 8 — Troubleshooting

Every problem below appears as a **"Partial results"** box on the report — the check still finishes,
and it never silently looks clean.

| Warning on the report | Cause | Fix |
|---|---|---|
| *"The web wasn't searched: no SearXNG server is configured (SEARXNG_URL)"* | Variable missing, or the app wasn't redeployed. | Part 4. |
| *"SearXNG refused the search request. Check that `json` is listed…"* | JSON output disabled. | Part 3 — the file mount. |
| *"SearXNG isn't responding, so web results are partial."* | SearXNG is down, or the hostname/port is wrong. | Check the SearXNG service is running; re-check `SEARXNG_URL`. |
| *"…returned nothing even for a phrase that is certainly online, so its search engines are probably blocking this server."* | Google/Bing/DuckDuckGo are CAPTCHA-ing or rate-limiting your server's IP. | See below. |
| *"…stopped returning results partway through the check…"* | The engines began rate-limiting mid-check. | Same as above; also avoid many checks at once. |
| *"SearXNG's rate limit was reached…"* | SearXNG's own limiter is on. | Make sure `limiter: false` is in the mounted settings file. |
| *"Your chapters have too few long sentences to search the web with…"* | The text is mostly short fragments or lists. | Not a fault — there's nothing to search on. |
| Progress page: *"This check hasn't started yet"* | Queue workers aren't running. | Part 5 — restart the app container. |
| Failed with *"The check never started…"* | Same, after 5 minutes. | Same. |
| Failed with *"The check took too long and was stopped"* | A worker died mid-check. | Run it again; if it repeats, check the app Logs. |

### If Google/Bing block your server
This is the main risk of the design: SearXNG works by asking Google, Bing, DuckDuckGo and others on
your behalf, and **datacenter IP addresses (like a VPS) get blocked far more readily than home
connections.** You can't know until you try. One engine being blocked is normal (DuckDuckGo showed a
CAPTCHA even on a home connection) and doesn't matter while others still answer. If *all* of them are
blocked:

1. Try again later — blocks are often temporary.
2. Lower the load: each user is limited to 5 checks per 10 minutes, and the checker pauses 1 second
   between searches (`similarity.sources.web.delay_ms` in `config/similarity.php`) — raise the pause.
3. Route SearXNG's outgoing requests through a proxy (SearXNG's `outgoing.proxies` setting). This is
   the standard fix, but it means a proxy you have to provide.
4. If it can't be made reliable, switch the feature off (Part 9) rather than leave it reporting nothing.

---

## Part 9 — Rolling back / turning it off

You never need to remove the database tables; they're harmless when unused.

- **Switch the feature off (fastest):** set `SIMILARITY_WEB_ENABLED=false` in the app's environment and
  redeploy. The card shows **"The web · Off"** and checks finish immediately without searching.
- **Or** remove `SEARXNG_URL` and redeploy → the card shows **"Not set up"**.
- **Emergency:** stopping the SearXNG service is also safe — checks then report *"SearXNG isn't
  responding"* instead of spinning.
- **Full code rollback:** redeploy the previous commit. Old code with the new tables present is fine.
  Only if you truly need to remove the tables (this **deletes all stored checks**), and the similarity
  migration is the latest one: `php artisan migrate:rollback --step=1`.

---

## Part 10 — Ongoing care

- **Pin and update SearXNG.** After the first working deploy, change `:latest` to a specific version
  tag so a redeploy can't surprise you. Search engines change their pages often and SearXNG must be
  updated to keep up — if checks start returning nothing months from now, updating the image is the
  first thing to try.
- **Workers are shared.** Checks use the same 2 queue workers as manuscript previews and emails. One
  check can occupy a worker for up to 8 minutes; two at once would delay everything else. Fine at a
  school's volume; if it ever isn't, the fix is a dedicated queue and worker for checks.
- **Storage grows.** Each check keeps an encrypted copy of the text it examined (so its report stays
  accurate after the chapters change). There's no automatic cleanup yet — fine for a long time at school
  scale, but worth pruning old checks eventually.
- **Never change `APP_KEY`.** Chapters and stored check documents are encrypted with it; a new key makes
  all existing content unreadable.
- **Privacy.** Short phrases (about 10 words each, about 24 per check) from researchers' unpublished
  chapters are sent to public search engines through SearXNG. Make sure the school is comfortable with
  that, and that the researcher-facing note (on the card) stays visible.

---

## Quick reference

| Setting | Where | Value |
|---|---|---|
| `SEARXNG_URL` | app env | `http://<searxng-internal-hostname>:8080` |
| `SEARXNG_LANGUAGE` | app env (optional) | `all` (default), or e.g. `en-US` |
| `SIMILARITY_WEB_ENABLED` | app env (optional) | `true` (default); `false` turns the feature off |
| `QUEUE_CONNECTION` | app env | `database` (already set — never `sync`) |
| `DB_QUEUE_RETRY_AFTER` | app env | `900` (already set — must exceed 720) |
| `SEARXNG_SECRET` | **SearXNG** service env | long random string |
| `/etc/searxng/settings.yml` | **SearXNG** file mount | contents of `docker/searxng/settings.yml` |
| Tuning | `config/similarity.php` | phrases per check, pages downloaded, pause between searches |
| Limits | built in | 5 checks / 10 min / user; one running check per research; 8-minute time cap |
