# Contributing

Thanks for contributing to the Mailmojo WordPress plugin!

This repository is the development home for the official WordPress.org plugin
“Mailmojo”.

## Project structure

- ./mailmojo is the deployable WordPress plugin (what ships to users / WP.org).
- Files in the repository root (README, AGENTS, CI config, etc.) are
  development-only and are not distributed to WordPress installs.

## Development setup

### Requirements

- Node.js (LTS)
- Docker
- PHP
- Composer

### Start local WordPress

From the repository root, run:

    npx @wordpress/env start

This will start WordPress using Docker.

Access details:
- Site: http://localhost:8888
- Admin: http://localhost:8888/wp-admin
- Username: admin
- Password: password

### Install plugin dependencies

    cd mailmojo
    npm install
    composer install

### Build the plugin

    npm run build

During active development, you can run:

    npm run start

Activate the plugin in WordPress:
- WP Admin → Plugins → Mailmojo

## Pull requests

Please keep pull requests small and focused.

Every PR should include:
- What changed
- Why it changed
- How to test (step-by-step)
- Screenshots or GIFs for admin UI or block/editor changes, when relevant

Avoid unrelated refactors in the same PR.

## Security and secrets (important)

Never log, print, or expose:
- Mailmojo access tokens
- WordPress application passwords (plaintext)
- Any credentials returned from Mailmojo APIs

If you discover a security vulnerability, do not open a public issue or PR.
See SECURITY.md for responsible disclosure instructions.

## Code style

- JavaScript: follow the defaults provided by @wordpress/scripts.
- PHP: follow WordPress coding conventions where practical.
- Keep public-facing interfaces stable:
  - block attributes
  - REST API routes
  - option names

## Commit messages

Use short, clear, imperative commit messages.

Examples:
- Add setup page for Mailmojo token
- Inject Mailmojo SDK in block editor
- Create application password for content sync

Avoid vague messages like:
- wip
- updates
- fixes