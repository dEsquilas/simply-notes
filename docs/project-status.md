# Project status and how to continue

Last updated: 2026-09-13. Production runs commit `6484a59` (see `git log main`).

## Branches and delivery

- **`develop`** is the default working branch. Pushing to it runs the CI only.
- **`main`** is production. Every push to `main` runs the CI and, if everything is green, deploys automatically.
- No pull requests: work on `develop`, then merge `develop` into `main` when a change should go live.
- The old `develop/rwd` branch was renamed to `dev/rwd` (it is fully merged into `main`).

## CI and automatic deploy

Everything lives in `.github/workflows/tests.yml`:

- **Backend**: Pest unit, feature and architecture tests with a 100% coverage gate (`--min=100`).
- **Browser**: Pest Browser (Playwright) tests, split into 3 shards.
- **Deploy** (only on pushes to `main`, after backend and browser pass): same approach as the train-with project, using `appleboy/ssh-action`. On the server it runs, in order: `artisan down`, `git pull`, clears stale `bootstrap/cache/*.php`, `composer install --no-dev`, `artisan migrate --force` (only pending migrations, never fresh/refresh), config/route/view caches, `pnpm install --frozen-lockfile && pnpm run build`, `queue:restart`, `artisan up`. If any step fails, the site stays in maintenance mode and the job fails.
- Only pull request runs cancel older runs, so a deploy on `main` is never cancelled halfway.
- Repository secrets: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PATH` (the SSH key is the same deploy key train-with uses).

## Production server

- Host `funky.desquilas.me` (AlmaLinux + CentOS Web Panel), app in `/home/dani/notes.desquilas.me`.
- PHP: use **`php84`**; plain `php` on that server is 8.2. The site is served by a dedicated PHP-FPM 8.4 pool.
- Scheduler: the user crontab runs `php84 artisan schedule:run` every minute. It triggers `trash:purge` and `versions:prune` daily at 00:00.
- Database backups are expected to run daily on the server; the deploy itself does not take one.

## Features added on 2026-09-13

- **Hideable notes list** on desktop (state kept in localStorage).
- **Trash on soft deletes** (`deleted_at` on notes and notebooks): restore, permanent delete, empty trash, and an automatic purge after `config/trash.php` days (30).
- **Note versioning** (`note_versions`, encrypted like notes). A version is stored before edits: when editing starts after inactivity, before a substantial change (pinned), when an editing session ends, manually with a label (pinned), and before a restore (pinned). `versions:prune` keeps everything for 7 days, one per day up to 3 months, then one per ISO week; pinned versions are never pruned. Thresholds live in `config/versions.php`.
- **Editor: Tiptap 3** replaced Quill. Legacy Quill and Evernote markup is converted when a note is loaded (`resources/js/components/editor/legacy-content.js`). Toolbar icons are Quill's SVGs (`icons.js`). All 1565 production notes were validated against the previous editor with no content loss.

## Local development

- Sail: app on http://localhost:8095, MySQL on port 3327 (database `simply_notes`). Local data is real and not disposable: run only plain `migrate`, never destructive commands.
- Tests use SQLite in memory (`phpunit.xml`). Browser tests need built assets: `pnpm run build`, then `vendor/bin/pest --testsuite=Browser`. Stop any Vite dev server first, otherwise `public/hot` makes the tests load assets from it.
- Backend: `vendor/bin/pest --testsuite=Unit,Feature,Arch --parallel --coverage --min=100`.
- `scripts/tiptap-validation/` and `tests/browser/generators/` contain the tooling used to compare every note between the old Quill editor and Tiptap (instructions in their headers). Their output goes to `data/`, which is gitignored because it holds decrypted notes.

## Pending work

1. The note version preview (history panel) looks off and does not match the editor.
2. The UI mixes English and Spanish: unify the language and translate the whole app. New texts stay in English for now.
3. Large Evernote imports can fail: uploads allow 1 GB but the PHP pool allows 128 MB and 30 s, and imports run synchronously (`QUEUE_CONNECTION=sync`). Raise the limits or move imports to a queue worker.
4. Tiptap does not convert Quill's paragraph-level indentation (`ql-indent-N` on non-list blocks). No production note uses it today.
