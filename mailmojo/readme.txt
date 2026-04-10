=== Mailmojo for WordPress ===
Contributors:      mailmojo
Tags:              newsletter, email marketing, forms, popup, content-sync
Requires at least: 5.8
Requires PHP:      8.2
Tested up to:      6.9
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to Mailmojo — Norway's email marketing platform — and turn your content into newsletters with less effort.

== Description ==

**Mailmojo** is a Norwegian email and SMS marketing platform built for businesses
that want professional results without the complexity. This official plugin bridges
your WordPress site and your Mailmojo account in three practical ways.

**Grow your list with subscribe popups**
Once the plugin is installed and connected with your Mailmojo account, your
published subscribe popups appear on your site automatically, no extra setup
needed. The plugin loads a small script in the background so your popups just
work, respecting the display rules you've already configured in Mailmojo. Popups
set to show site-wide will appear everywhere; those targeting specific URLs will
appear only on the right pages.

**Add subscribe buttons anywhere**
Use the Mailmojo Popup Button block to place a subscribe button in any post or
page. Connect it to any published popup form in your Mailmojo account, and
visitors who click it will see the form immediately. It fits naturally into the
block editor alongside your other content.

**Turn your posts into newsletter content**
Enable content sync to push your published WordPress posts to Mailmojo and keep
them in sync automatically. When you're building a newsletter, Mailmojo's content
browser lets you drag and drop your latest posts directly into your email, no
copying, no pasting, no reformatting.

== Installation ==

1. Upload the `mailmojo` folder to `/wp-content/plugins/`, or search for
   "Mailmojo" and install directly from the WordPress plugin directory.
2. Activate the plugin from the Plugins screen.
3. Go to **Mailmojo** in the WordPress admin menu and enter your API token.
   (Find your token under the WordPress integration in your Mailmojo account.)
4. Your published Mailmojo popups will now appear on your site automatically.
5. Optionally, add a **Mailmojo Popup Button** block to any post or page to let
   visitors trigger a specific popup with a click.
6. To sync your WordPress content to Mailmojo, enable content sync on the
   settings page.

== Frequently Asked Questions ==

= Do I need a Mailmojo account? =

Yes. You need an active Mailmojo account to use this plugin.
Don't have one yet? Sign up at mailmojo.no.

= Will my published popups show up automatically? =

Yes. As soon as the plugin is connected to your Mailmojo account, your published
popup forms will display according to the rules you've configured in Mailmojo.
Site-wide popups will appear across your whole site, and URL-specific popups will
only show on the pages you've targeted. No extra configuration required in
WordPress.

= What's the difference between automatic popups and the Popup Button block? =

Automatic popups follow their own display rules (such as appearing after a delay
or on scroll) and show up wherever you've configured them in Mailmojo. The Popup
Button block lets you place a specific button in your content that opens a chosen
popup on demand when a visitor clicks it.

= What does content sync actually send to Mailmojo? =

Content sync sends your published WordPress posts to Mailmojo and keeps them
updated as you publish or edit. This makes your posts available in Mailmojo's
content browser so you can drag them into newsletter layouts without leaving
Mailmojo.

= Does content sync share my WordPress password? =

No. Content sync authenticates using a WordPress application password created
specifically for the Mailmojo integration. Your main WordPress password is
never shared.

= Does the plugin add scripts to every page? =

Yes, the script is what enables the automatic display of popup subscribe forms.
The script is only loaded on public-facing pages, not in the WordPress admin.

== Screenshots ==

1. Plugin settings page in the WordPress admin.
2. Popup Button block in the block editor.
3. Example of a Mailmojo subscribe popup shown on a site.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Automatic Mailmojo popup display via script on public pages.
* Mailmojo Popup Button block for triggering popups on click.
* Optional WordPress content sync to Mailmojo.