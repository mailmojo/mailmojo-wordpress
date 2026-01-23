# Mailmojo WordPress Plugin

This repository contains the official Mailmojo plugin for WordPress.

- GitHub repository: mailmojo-wordpress (development)
- WordPress.org plugin slug: mailmojo
- Installed plugin folder: wp-content/plugins/mailmojo/
- Deployable plugin source: ./mailmojo

This plugin is intended to be the canonical Mailmojo integration for WordPress.

-------------------------------------------------------------------------------

## What this plugin does

Current scope (initial versions):
- Gutenberg blocks for embedding Mailmojo signup forms
- Automatic loading of the Mailmojo JavaScript SDK
  - Embedded forms
  - Popup forms
  - Future SDK-driven features

Planned / upcoming:
- Content synchronization from WordPress to Mailmojo
  - Posts (initially)
  - Products (WooCommerce, later)

-------------------------------------------------------------------------------

## Requirements

For development:
- Node.js (LTS)
- Docker
- PHP
- Composer

End users do not need Node, Docker, or Composer.

-------------------------------------------------------------------------------

## Local development

This project uses @wordpress/env to run WordPress locally via Docker.

Start WordPress (from the repository root):

    npx @wordpress/env start

WordPress will be available at:
- Site: http://localhost:8888
- Admin: http://localhost:8888/wp-admin
- Username: admin
- Password: password

Install plugin dependencies:

    cd mailmojo
    npm install
    composer install

Build the plugin:

    npm run build

Or, during active development:

    npm run start

Activate the plugin via:
- WP Admin → Plugins → Mailmojo

-------------------------------------------------------------------------------

## Project structure

    mailmojo-wordpress/
    ├── AGENTS.md              (Dev / agent guidelines; not deployed)
    ├── CONTRIBUTING.md
    ├── README.md              (This file; dev-facing)
    ├── SECURITY.md
    ├── .github/
    ├── .wp-env.json
    └── mailmojo/              (The WordPress plugin; deployable)
        ├── mailmojo.php
        ├── readme.txt         (WordPress.org readme)
        ├── block.json
        ├── src/
        ├── build/
        ├── vendor/
        └── composer.json

Only the contents of ./mailmojo are shipped to WordPress.org and end users.

-------------------------------------------------------------------------------

## Build and distribution

- JavaScript assets are built using @wordpress/scripts
- Source files live in mailmojo/src
- Compiled assets are output to mailmojo/build
- PHP dependencies are installed via Composer into mailmojo/vendor

Releases are deployed to WordPress.org via CI-only Subversion.

Developers do not use Subversion locally.

-------------------------------------------------------------------------------

## Security

Please report security issues privately.

Do not open public issues or pull requests for vulnerabilities.

See SECURITY.md for responsible disclosure instructions.

-------------------------------------------------------------------------------

## Contributing

Contributions are welcome.

Please read:
- CONTRIBUTING.md for workflow and expectations
- AGENTS.md for guidelines for both human contributors and coding agents

Keep pull requests focused, well-described, and reviewable.

-------------------------------------------------------------------------------

## License

This project is licensed under the GNU General Public License v2.0 or later.