Guidelines for humans and coding agents working on this repository.

## Scope & structure

- The deployable WordPress plugin lives in `./mailmojo`.
- Repo root files (`.github/`, `AGENTS.md`, docs) are development-only.

## PR expectations

Every PR should include:
- **What** changed
- **Why** it changed
- **How** to test (step-by-step)
- Any user-facing changes (screenshots for admin UI / blocks)

Keep PRs small and focused. Avoid unrelated refactors.

## Security & secrets (critical)

Never log or print:
- Mailmojo access tokens
- WordPress application passwords (plaintext)
- Any credentials returned from Mailmojo APIs

When handling secrets:
- Store only what’s necessary
- Prefer WordPress options APIs with capability checks
- Avoid exposing secrets in REST responses

## Commit messages

Use clear, imperative commit messages:
- Good: `Add setup page for Mailmojo token`
- Good: `Create Application Password for content sync`
- Bad: `updates` / `wip`

## Testing checklist (minimum)

Before opening a PR:
- `npm run build` (plugin)
- Verify plugin activates cleanly in a fresh wp-env site
- Validate admin pages load without PHP notices/warnings
- If blocks changed: insert block in editor + verify frontend render

When modifying any block source or assets that impact the build output,
always run `npm run build` so `mailmojo/build/*` stays in sync.

## WordPress Docker-free test harness (Codex/CI)

Use the Docker-free harness when running WordPress locally in Codex or CI:

### Prerequisites
- `php` (with the `sqlite3` extension enabled)
- `wp` (WP-CLI) available on the PATH
- A SQLite `db.php` drop-in (default: `/opt/tools/wp-sqlite-db/db.php`, or override via `WP_SQLITE_DROPIN`)

### Commands
From the repo root:
1. **Start WordPress:** `npm run wp:start`
2. **Stop WordPress:** `npm run wp:stop`
3. **Reset WordPress (wipe DB):** `npm run wp:reset`

The harness provisions:
- WordPress at `http://localhost:8888`
- Admin credentials: **admin / password**
- The `mailmojo` plugin is symlinked and activated by default
- The SQLite drop-in must be available locally (no internet download)

### How future E2E tests will hook in
E2E tooling (e.g. Playwright) should:
1. Run `npm run wp:start`
2. Hit `http://localhost:8888/wp-admin` and log in with the seeded admin credentials
3. Run UI flows
4. Run `npm run wp:stop` or `npm run wp:reset` to clean up
