# Mailmojo WordPress Plugin

This repository contains the source code for the official Mailmojo plugin for WordPress.

- GitHub repository: https://github.com/mailmojo/mailmojo-wordpress
- WordPress.org plugin slug: `mailmojo`
- Installed plugin folder: `wp-content/plugins/mailmojo/`
- Deployable plugin source: `./mailmojo`

-------------------------------------------------------------------------------

## What this plugin does

- Automatic display of Mailmojo subscribe popups on public pages via a small
  JavaScript snippet loaded from your Mailmojo account.
- A **Mailmojo Popup Button** Gutenberg block that opens a specific published
  popup form when clicked.
- Optional **content sync** that pushes published WordPress posts to Mailmojo
  so they can be dropped into newsletters from Mailmojo's content browser.

See [mailmojo/readme.txt](mailmojo/readme.txt) for the user-facing description.

-------------------------------------------------------------------------------

## Build requirements

- Node.js (LTS)
- npm
- PHP 8.2 or newer
- Composer
- Docker (only required for local WordPress via `@wordpress/env`)

End users do not need any of these — they install the plugin from the
WordPress.org directory or the packaged zip.

-------------------------------------------------------------------------------

## Building the plugin

All build commands run from inside the `mailmojo/` directory.

Install dependencies:

    cd mailmojo
    npm install
    composer install

Build JavaScript/CSS assets:

    npm run build

This uses `@wordpress/scripts` and outputs compiled assets to `mailmojo/build/`,
including a generated `build/blocks-manifest.php`.

During active development you can use the watch mode:

    npm run start

### Producing a distributable zip

From the repository root:

    npm run plugin:package

This runs [bin/package-plugin.sh](bin/package-plugin.sh), which:

1. Runs `composer install --no-dev --optimize-autoloader` inside `mailmojo/`.
2. Runs `npm run build` inside `mailmojo/`.
3. Copies the plugin source to `release/mailmojo/`, excluding development
   files (`src/`, `node_modules/`, `composer.lock`, `package.json`,
   `phpcs.xml`, dotfiles, Markdown, shell scripts, tests).
4. Produces `dist/mailmojo-<version>.zip`.

The resulting zip matches what is deployed to WordPress.org.

-------------------------------------------------------------------------------

## Local development with WordPress

The repository is set up to run WordPress locally via
[`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/),
which uses Docker.

From the repository root:

    npx @wordpress/env start

WordPress will be available at http://localhost:8888 (admin:
`admin` / `password`).

Note: [.wp-env.json](.wp-env.json) sets `WP_HOME` and `MAILMOJO_API_BASE_URL`
to values used by the Mailmojo team's internal development setup
(`wp.dev.local`, `api.mailmojo.local`). For a clean external build check the
site loads at `localhost:8888` via the admin, but full popup/API flows
require overriding those or connecting to the real Mailmojo API.

To stop:

    npx @wordpress/env stop

-------------------------------------------------------------------------------

## Project structure

    mailmojo-wordpress/
    ├── README.md                 (This file; dev-facing)
    ├── CONTRIBUTING.md
    ├── SECURITY.md
    ├── AGENTS.md                 (Guidelines for coding agents)
    ├── LICENSE
    ├── .wp-env.json              (Local WordPress config for @wordpress/env)
    ├── .github/workflows/        (CI, including WordPress.org deploy)
    ├── bin/                      (Packaging and i18n scripts)
    ├── wp-assets/                (Banner/icon/screenshots for WordPress.org)
    └── mailmojo/                 (The plugin — deployable source)
        ├── mailmojo.php          (Plugin entry)
        ├── uninstall.php
        ├── readme.txt            (WordPress.org readme)
        ├── composer.json
        ├── package.json
        ├── phpcs.xml
        ├── includes/             (PHP classes: API, admin, sync)
        ├── src/mailmojo/         (Block source: block.json, JS, SCSS)
        ├── build/                (Built assets, generated)
        ├── assets/
        ├── languages/
        └── vendor/               (Composer dependencies, generated)

Only the contents of `./mailmojo` are shipped to WordPress.org and end users.

-------------------------------------------------------------------------------

## Linting and translations

- PHPCS is configured via [mailmojo/phpcs.xml](mailmojo/phpcs.xml) using the
  WordPress Coding Standards. After `composer install`, run from `mailmojo/`:

        ./vendor/bin/phpcs

- JavaScript and CSS linting via `@wordpress/scripts`:

        npm run lint:js
        npm run lint:css

- Translation files can be regenerated via:

        npm run plugin:i18n

-------------------------------------------------------------------------------

## WordPress.org release automation

Releases are deployed to WordPress.org via GitHub Actions using
[`10up/action-wordpress-plugin-deploy`](https://github.com/10up/action-wordpress-plugin-deploy).
Developers do not use Subversion locally.

Workflow: [.github/workflows/deploy-wordpress-org.yml](.github/workflows/deploy-wordpress-org.yml)

### One-time setup (GitHub repository settings)

Add these repository secrets:

- `WP_ORG_SVN_USERNAME` — WordPress.org username with commit access to the plugin slug
- `WP_ORG_SVN_PASSWORD` — WordPress.org application password

### Release process

1. Update the version in:
   - [mailmojo/mailmojo.php](mailmojo/mailmojo.php) (`Version:` header)
   - [mailmojo/readme.txt](mailmojo/readme.txt) (`Stable tag:`)
2. Add a matching entry to the `== Changelog ==` section of
   [mailmojo/readme.txt](mailmojo/readme.txt).
3. Commit changes to `main`.
4. Create and push a semantic tag with a `v` prefix:

        git tag v1.0.1
        git push origin v1.0.1

The workflow validates that the tag, plugin version, and stable tag all match
before deploying.

-------------------------------------------------------------------------------

## Security

Please report security issues privately. Do not open public issues or pull
requests for vulnerabilities. See [SECURITY.md](SECURITY.md) for responsible
disclosure instructions.

-------------------------------------------------------------------------------

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) for
workflow and expectations. Keep pull requests focused, well-described, and
reviewable.

-------------------------------------------------------------------------------

## License

This project is licensed under the GNU General Public License v2.0 or later.
See [LICENSE](LICENSE).
