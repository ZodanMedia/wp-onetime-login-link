=== Zodan One-time Login Link ===
Contributors: martenmoolenaar, zodannl
Plugin URI: https://plugins.zodan.nl/wordpress-onetime-login-link/
Donate link: https://www.buymeacoffee.com/zodan
Tags: direct login, fast login, no password, theme development, development
Requires at least: 5.5
Tested up to: 6.9
Description: Let users login once without a password
Version: 0.0.8
Stable tag: 0.0.8
Author: Zodan
Author URI: https://zodan.nl
Text Domain: zodan-onetime-login-link
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Allow users to securely log in *once* without a password.


== Description ==

🔗 Create secure, self-expiring, automatic login links for existing WordPress users. Login works just by opening the link.

Using the One-time Login Link, users can login without username or password.

You can customize when the login expires and which roles cannot use the One-time Login Link.

= Why? =
In projects, we sometimes encounter (groups of) users struggling with the sheer number of passwords they have to manage.
Naturally, we prefer good password management, combined with other, additional security layers. However, we'd like to accommodate this group.

This plugin allows you to log in *securely* without a password.


= What does it do? =

* In the user list, the plugin creates a ‘Send login once link’ for each user.
* The link creates a personal key linked to the user that can be used to log in temporarily (maximum 15 minutes).
* The link will be sent to the user via email.

Furthermore, the website administrator can customize the settings for
* The link expiration time
* Whether or not users can request a link themselves and if,
* If rate limiting is needed (e.g. no more than once every 5 minutes)

This plugin is under active development. Any feature requests are welcome at [plugins@zodan.nl](plugins@zodan.nl)!



== Installation ==

= Install the One-time Login Link from within WordPress =

1. Visit the plugins page within your dashboard and select ‘Add New’;
2. Search for ‘Zodan One-time Login Link’;
3. Activate the plugin from your Plugins page;
4. Go to ‘after activation’ below.

= Install manually =

1. Unzip the One-time Login Link zip file
2. Upload the unzipped folder to the /wp-content/plugins/ directory;
3. Activate the plugin through the ‘Plugins’ menu in WordPress;
4. Go to ‘after activation’ below.

= After activation =

1. On the Plugins page in WordPress you will see a 'settings' link below the plugin name;
2. On the One-time Login Link settings page:
**  Select which user roles to exclude
**  Set the mail settings for the mail with the link users receive
**  Optionally, take a look at the other settings
3. Save your settings and you’re done!


== Frequently asked questions ==

= Does it work in a multisite environment? =

Yep. It does.

= Do you have plans to improve the plugin? =

We currently have on our roadmap:
* Improve speed (using a separate -indexed- table)
* Improve logging
* Improve UX (e.g. adding a progress bar)
* Adding translations

If you have a feature suggestion, send us an email at [plugins@zodan.nl](plugins@zodan.nl).


== Screenshots ==

1. Roles section of the Settings page where you can exclude roles.
2. Mail settings section where you can customize the mail that will be sent to the user when a One-time Login Link is created.
3. Other options for customizing the plugin, like the Link expiration time, whether or not users can request a link themselves and settings for the rate limits for these requests.
4. The link that appears with the user in the list on the Users admin page. Simply click the link to send the users a new login token.


== Changelog ==

= 0.0.8 =
* Fix bug with fingerprint mismatch due to not cleaning up old hashes
* Small code optimizations

= 0.0.7 =
* Changed the way the request link was added to the login page
* Name change to satisfy the WP plugin check
* Cleaned up the settings page

= 0.0.6 =
* Optimized logging functions for bulk mailing

= 0.0.5 =
* Added a link in the admin panel to send an email with a One-time Login Link to all users at once

= 0.0.4 =
* Solved verify issue with possible multiple users

= 0.0.3 =
* Added settings for customization of link expiration time and rate limiting.

= 0.0.2 =
* Added possibility to request a login link (on the login screen).

= 0.0.1 =
* Very first version of this plugin.
