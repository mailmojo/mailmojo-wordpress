=== Mailmojo ===
Contributors:      mailmojo
Tags:              newsletter, forms, popup, block, content-sync
Requires at least: 5.8
Tested up to:      6.9
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

This is the official WordPress plugin for connecting your site to Mailmojo.
It lets you add a block for choosing a published Mailmojo popup form, and also
allows syncing your WordPress content to Mailmojo so creating newsletters is
much faster.

== Description ==

The plugin gives you three core pieces of functionality:

* A block you can insert into posts and pages and link to a published Mailmojo popup form.
* Optional content sync that makes WordPress posts and pages available to Mailmojo.
* An admin setup page for saving your Mailmojo access token and managing sync.

== Installation ==

1. Upload the `mailmojo` folder to `/wp-content/plugins/`, or install the plugin
   through the WordPress admin.
2. Activate the plugin from the Plugins screen.
3. Open the Mailmojo admin page in WordPress and save your Mailmojo access token.
4. Add the Mailmojo Popup Button block to any post or page where you want a
   subscribe button.
5. Optionally enable content sync if you want WordPress posts and pages pushed to
   Mailmojo.

== Frequently Asked Questions ==

= Do I need a Mailmojo account? =

Yes. You need a Mailmojo account and API token to connect the plugin to your site.

= Does the plugin load the Mailmojo SDK on the front end? =

Yes. The SDK snippet is stored in WordPress and printed on public site pages only,
not in the WordPress admin.

= Does content sync share my WordPress password? =

No. Content sync uses a WordPress application password created specifically for
the Mailmojo integration.

== Screenshots ==

1. Mailmojo plugin settings page in WordPress admin.
2. Mailmojo Popup Button block in the editor.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Add Mailmojo admin setup page and SDK snippet loading.
* Add Mailmojo Popup Button block.
* Add optional WordPress content sync.
