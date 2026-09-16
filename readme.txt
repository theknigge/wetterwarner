=== Wetterwarner ===
Contributors: bocanegra
Donate link: https://wetterwarner.de/unterstuetzen/
Tags: weather, weather warnings, dwd, germany, block
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.0
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
* Optional: current DWD warning map for Germany or a federal state
* Background updates and local caching – fast page loads and gentle on the data source
* Privacy friendly: visitors never connect to external servers, the warning map is served from your own site
* No jQuery or icon fonts in the frontend

[Website & documentation](https://wetterwarner.de/dokumentation/)

= Shortcode =

`[wetterwarner region="103241000" map="60"]`

Available attributes:

* `region` – warncell ID of the region (required) or `demo` for sample warnings
* `title`, `intro`, `no_warnings` – texts, `%region%` is replaced by the name of the region
* `max` – maximum number of warnings (default 3, 0 = all)
* `show_always` – show the output even without warnings (1/0)
* `validity`, `details`, `icons`, `colors`, `link`, `hide_duplicates` – display options (1/0)
* `map` – width of the warning map in percent (0 = no map)
* `map_region` – `auto`, `de`, `baw`, `bay`, `bbb`, `hes`, `mvp`, `nib`, `nrw`, `rps`, `sac`, `saa`, `shh`, `thu`

You can find the warncell ID with the region search in the block or widget.

= External services =

This plugin relies on the **Wetterwarner API** (`api.wetterwarner.de`), a service operated by the plugin author. The API caches the official warnings of the German Weather Service centrally, so that not every website has to download the DWD data, which can be several megabytes in size.

All requests are made server-side by your WordPress installation – at most every 5 minutes, with all regions in use combined into a single request. Visitors of your website never connect to external servers.

* `https://api.wetterwarner.de/v3/warnings` – warnings for the regions in use. Transmitted: the warncell IDs of these regions and, for technical reasons, the IP address of your web server.
* `https://api.wetterwarner.de/v3/regions` – region search in the block and widget editor. Transmitted: the search term.
* `https://api.wetterwarner.de/v3/maps/` – warning maps, only if a map is displayed.

No data about the visitors of your website is transmitted.

**Optional: support development** – Only if you explicitly agree (notice in the admin area or "Settings > Wetterwarner"), the address of your website as well as the plugin, WordPress and PHP versions are additionally sent with the regular request. You can revoke your consent at any time; the stored information is then deleted. Entries without contact are removed automatically after 90 days.

Provider: Tim Knigge, IT93 – [Privacy policy](https://wetterwarner.de/datenschutz)

The API address can be changed with the constant `WETTERWARNER_API_URL` or the filter `wetterwarner_api_url`, e.g. for a self-hosted instance.

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
If you use a caching plugin, pages may be cached for longer. Under "Settings > Wetterwarner" you can see when the data was last loaded.

= What happens if the Wetterwarner API is not reachable? =
The last loaded warnings remain visible for up to two hours. After that, a notice with a link to the current warnings on dwd.de is shown instead of outdated warnings. "Tools > Site Health" reports the outage as critical.

= How can I contact the developer or report a bug? =
Please use the WordPress [support forum](https://wordpress.org/support/plugin/wetterwarner/) or the [contact form](https://it93.de/kontakt/).

== Screenshots ==

1. Wetterwarner in the frontend
2. Block with region search in the editor
3. Settings

== Upgrade Notice ==

= 3.0.0 =
Major update: official DWD warnings via the new Wetterwarner API, block and shortcode. Requires WordPress 6.3 and PHP 7.4. Existing widgets are migrated automatically – please check them briefly after the update.

== Changelog ==

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
