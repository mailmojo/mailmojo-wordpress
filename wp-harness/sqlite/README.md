# SQLite drop-in

This harness expects a `db.php` SQLite drop-in that provides WordPress database support
without MySQL.

By default the harness looks for the drop-in at `/opt/tools/wp-sqlite-db/db.php`.
You can override this by setting `WP_SQLITE_DROPIN` to the file path before running
`npm run wp:start`.

Suggested sources (must be provided by the environment; no internet download is performed
by the harness):
- `wp-sqlite-db` drop-in
- WordPress SQLite Database Integration drop-in
