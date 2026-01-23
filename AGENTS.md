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