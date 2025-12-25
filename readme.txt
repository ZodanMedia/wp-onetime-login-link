=== Z User Onetime Login ===
Contributors: martenmoolenaar, zodannl
Plugin URI: https://plugins.zodan.nl/wordpress-user-onetime-login/
Donate link: https://www.buymeacoffee.com/zodan
Tags: user, login, direct login, theme development, development
Requires at least: 5.5
Tested up to: 6.9
Description: Let users login once without a password
Version: 0.0.1
Stable tag: 0.0.1
Author: Zodan
Author URI: https://zodan.nl
Text Domain: z-user-onetime-login
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Allow users to login once without a password.

== Description ==

When we are developing themes, we quite often like to switch between the old (active) and the new (to develop) version of a theme. Sometimes without other people noticing.
This plugin does exactly that.


= What does it do? =

It lets users with certain roles see another (than the currently active) theme, by 
* Selecting a theme from the list of installed themes
* Selecting which user roles are permitted to switch themes and
* Optionally, selecting user roles that can use a 'switch theme/back' button on the front-end

This plugin is under active development. Any feature requests are welcome at [plugins@zodan.nl](plugins@zodan.nl)!



== Installation ==

= Install the One-time Login Link from within WordPress =

1. Visit the plugins page within your dashboard and select ‘Add New’;
1. Search for ‘Z One-time Login Link’;
1. Activate the plugin from your Plugins page;
1. Go to ‘after activation’ below.

= Install manually =

1. Unzip the One-time Login Link zip file
2. Upload the unzipped folder to the /wp-content/plugins/ directory;
3. Activate the plugin through the ‘Plugins’ menu in WordPress;
4. Go to ‘after activation’ below.


= After activation =

1. On the Plugins page in WordPress you will see a 'settings' link below the plugin name;
2. On the One-time Login Link settings page:
**  Select which user roles to exclude
**  Set the mail settings
3. Save your settings and you’re done!



== Frequently asked questions ==

= Does it work in a multisite environment? =

Yep. It does.

= The Switch theme button on the front-end is not showing, can you help? =



= Do you have plans to improve the plugin? =

We currently have on our roadmap:
* Adding translations
* Addin
* Set the preference per user

If you have a feature suggestion, send us an email at [plugins@zodan.nl](plugins@zodan.nl).




== Changelog ==

= 0.0.1 =
* Very first version of this plugin
