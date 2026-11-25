=== Wetterwarner ===
Contributors: bocanegra
Donate link: https://wetterwarner.de/unterstuetzen/
Tags: weather, weather warnings, dwd, germany, block
Requires at least: 6.3
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 3.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Official weather warnings from the German Weather Service (DWD) for your region – as a block, shortcode or widget.

== Description ==

Wetterwarner displays the official weather warnings of the German Weather Service (Deutscher Wetterdienst, DWD) for any warning region in Germany – from a district down to a single municipality.

= Features =

* **Block** for the block editor and block widget areas, with live preview
* **Shortcode** `[wetterwarner]` for page builders and classic themes
* **Classic widget** for older themes
* More than 11,000 DWD warning regions (districts, municipalities, coasts, inland lakes) with a convenient search
* Colour coding by warning level, icons, validity period and expandable details with safety instructions
* Prior information (Vorabinformation) is marked as such
* **Warning banner:** current warnings at the top or bottom of every page
* Filters: minimum warning level, types of warnings, and whether prior information is shown
* Stays up to date with page caching: warnings are refreshed in the browser from your own website
* Optional: current DWD warning map for Germany or a federal state
* Background updates and local caching – fast page loads and gentle on the data source
* Privacy friendly: visitors never connect to external servers, the warning map is served from your own site
* No jQuery or icon fonts in the frontend

[Website, documentation & Demo](https://wetterwarner.de)

= Wetterwarner Supporter license =

The Wetterwarner plugin is free and fully functional – with the same official DWD data for everyone. It is developed and maintained in my spare time, and the Wetterwarner API that supplies the data to every installation has real running costs. The optional **Wetterwarner Supporter license** is a way to directly support that ongoing development and operation – and as a thank-you, it also gives your site a few practical extras:

* **Faster updates:** new warnings every 5 instead of 15 minutes
* **Fallback server:** if the main API is unavailable, your site automatically switches to a separate, independently hosted server
* **Priority support** via contact form or e-mail

**Launch offer: 19 € per year** (regular price 29 € per year). One-time purchase for 12 months, no subscription, no automatic renewal. The license is tied to your website's address and is activated automatically with the next update – no license key to copy.

[Get your Supporter license](https://wetterwarner.de/unterstuetzen/#supporter) – or directly from "Settings > Wetterwarner" in your WordPress admin.

= External services =

This plugin relies on the **Wetterwarner API** (`api.wetterwarner.de`), a service operated by the plugin author. The API caches the official warnings of the German Weather Service centrally, so that not every website has to download the DWD data, which can be several megabytes in size.

All requests are made server-side by your WordPress installation – at the interval specified by the API (currently every 15 minutes, with a Supporter license every 5 minutes), with all regions in use combined into a single request. Visitors of your website never connect to external servers. No data about the visitors of your website is transmitted.

With every request for warnings and warning maps from the fallback server, and with every request for warnings from the main server, the address of your website (host name and path) is sent so that a Supporter license can be assigned to it. Without a license or your consent (see below), this address is not stored by the API.

**Optional: support development** – Only if you explicitly agree (notice in the admin area or "Settings > Wetterwarner"), the address of your website as well as the plugin, WordPress and PHP versions are additionally sent with the regular request and stored for the usage statistics. You can revoke your consent at any time; the stored information is then deleted. Entries without contact are removed automatically after 90 days.

Provider: Tim Knigge, IT93 – [Privacy policy](https://wetterwarner.de/datenschutz)

Weather data: Deutscher Wetterdienst, Frankfurter Straße 135, 63067 Offenbach, Germany – [Terms of use](https://www.dwd.de/EN/service/copyright/copyright_node.html), [Privacy policy](https://www.dwd.de/EN/service/dataprotection/dataprotection_node.html).

= Important notes =

This plugin does not replace an official source of information. The German Weather Service gives no guarantee for the availability of its data. All rights to warnings and maps remain with the Deutscher Wetterdienst; the maps are not altered in content.

The plugin is intended for use in Germany. It is not affiliated with the mobile app of the same name.

= Credits =

* Icons from [Lucide](https://lucide.dev) (ISC license, warning triangle from Feather under the MIT license) – license texts in `licenses/lucide.txt`
* Warnings, warning maps and warning regions: Deutscher Wetterdienst, [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
* [wp-color-picker-alpha](https://github.com/kallookoo/wp-color-picker-alpha) (GPLv2)

== Installation ==

1. Install the plugin via "Plugins > Add New" or upload the folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Add the "Wetterwarner" block to a page or a widget area and select your warning region.

Colours per warning level can be changed under "Settings > Wetterwarner".

== Frequently Asked Questions ==

= How do I find my warning region? =
Simply type the name of your town or district into the region search of the block or widget. The search knows all DWD warning regions.

= District or municipality – which should I choose? =
Warnings for municipalities are more precise. District warnings combine all warnings within the district.

= How can I test my settings? =
Search for "demo" and select the sample region. Sample warnings of all warning levels are displayed.

= I updated from version 2.x. What do I have to do? =
Your widgets are migrated automatically. The old wettwarn.de feed IDs are assigned to the matching DWD warning region. If this is not possible for an ID, a notice appears in the admin area – simply select the region again.

= The warnings are not updated =
Even if a caching plugin delivers an older page, the warnings are refreshed in the visitor's browser from your own website (can be turned off under "Settings > Wetterwarner"). There you can also see when the data was last loaded.

= How do I show warnings on every page? =
Enable the warning banner under "Settings > Wetterwarner" and choose a region and a minimum warning level. Without a matching warning, nothing is displayed.

= Can I show only severe warnings? =
Yes. In the block, widget or shortcode you can set a minimum warning level, select the types of warnings and hide prior information. The warning map always shows all warnings.

= What happens if the Wetterwarner API is not reachable? =
The last loaded warnings remain visible for up to two hours. After that, a notice with a link to the current warnings on dwd.de is shown instead of outdated warnings. "Tools > Site Health" reports the outage as critical.

= How can I contact the developer or report a bug? =
Please use the WordPress [support forum](https://wordpress.org/support/plugin/wetterwarner/). With a [Wetterwarner Supporter license](https://wetterwarner.de/unterstuetzen/#supporter) you get priority support via the [contact form](https://it93.de/support/?betreff=wetterwarner) or support@it93.de.

== Screenshots ==

1. Warning banner at the top of every page
2. Weather alerts on a page
3. Block in the editor with filter options
4. Banner settings

== Upgrade Notice ==

= 3.2.0 =
New: warning banner for every page, filters by warning level and type, and current warnings even with page caching. Tested with WordPress 7.2.

= 3.1.0 =
New: Wetterwarner Supporter license – support the ongoing development and operation of Wetterwarner, and get updates every 5 minutes, an automatic fallback server and priority support in return. Launch offer: 19 € per year (regular price 29 €).

= 3.0.0 =
Major update: official DWD warnings via the new Wetterwarner API, block and shortcode. Requires WordPress 6.3 and PHP 7.4. Existing widgets are migrated automatically – please check them briefly after the update.

== Changelog ==

= 3.2.0 =
* New: **warning banner** – shows current warnings of one region at the top or bottom of every page
  * Minimum warning level, position and your own "Details" link
  * Visitors can close it; it reappears when the warnings change
* New: **filters** for block, shortcode and classic widget: minimum warning level, types of warnings and prior information
  * New shortcode attributes: `min_stage`, `types`, `prior`
  * If warnings are hidden by a filter, the text says so instead of claiming there are none
* New: **current despite page caching** – warnings are refreshed in the browser from your own website (can be turned off in the settings)
  * If the refresh fails, warnings older than two hours are replaced by a link to dwd.de
* New: update interval and fallback server notes in the settings, clearer Supporter overview and a renewal hint 30 days before a license expires
* Change: the update interval is now specified by the Wetterwarner API (currently every 15 minutes, with a Supporter license every 5 minutes)
* Compatibility with WordPress 7.2
* Bugfix: the stored license status is now removed on uninstall

= 3.1.0 =
* New: [Wetterwarner Supporter license](https://wetterwarner.de/unterstuetzen/#supporter) – support the ongoing development and operation of Wetterwarner, and get a few extras as a thank-you:
  * Faster updates: every 5 instead of 15 minutes
  * Automatic fallback server if the main API is unavailable
  * Priority support via contact form or e-mail
  * Launch offer: 19 € per year (regular price 29 € per year), one-time purchase, no subscription, activated automatically for your website
* New: license status and purchase link under "Settings > Wetterwarner"
* Change: without a Supporter license, background updates now run every 15 instead of 5 minutes to keep the free API sustainable

= 3.0.0 =
* New: "Wetterwarner" block with live preview in the editor
* New: shortcode `[wetterwarner]`
* New: official warnings of the German Weather Service via the Wetterwarner API (replaces the wettwarn.de RSS feeds)
* New: more than 11,000 DWD warning regions including municipalities, with search
* New: expandable details with description and safety instructions (replaces the tooltip)
* New: current municipality warning maps of the DWD, automatic selection of the federal state
* New: the warning map is cached in the uploads directory – no write access to the plugin folder needed
* New: data status and "Clear cache" in the settings
* Improvement: no Font Awesome, icon fonts or jQuery in the frontend
* Improvement: automatic migration of existing widgets from version 2.x
* Improvement: more accessible output (headings, screen reader texts, native details element)
* Requirements: WordPress 6.3 and PHP 7.4

= 2.8.1 =
* Bugfix: background colours of warnings
* Compatibility with WordPress 7.0

= 2.8 =
* Maintenance and security update
* Compatibility with WordPress 6.8 and 6.9

= 2.7.3 =
* Maintenance update
* Compatibility with WordPress 6.7

= 2.7.2 =
* Bugfix: data was only updated sporadically
* Bugfix: background tasks were scheduled multiple times

= 2.7.1 =
* Bugfix: wrong links of warnings
* Bugfix: widget checkbox settings were loaded incorrectly in some cases

= 2.7 =
* Improvement: required external data is now updated automatically in the background (WP-Cron)
* Bugfix: error message after update
* Further code improvements