# Plugin Check Report

**Plugin:** Wetterwarner
**Generated at:** 2026-09-16 11:31:29


## `build/wetterwarner/render.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 19 | 76 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'get_block_wrapper_attributes'. | [Dokumentation](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 20 | 34 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$wetterwarner_level'. | [Dokumentation](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 21 | 35 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$wetterwarner_level'. | [Dokumentation](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 22 | 27 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$wetterwarner_preview'. | [Dokumentation](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |

## `readme.txt`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | outdated_tested_upto_header | Getestet bis: 7.0 < 7.1. Der Wert „Tested up to“ in deinem Plugin ist nicht auf die aktuelle Version von WordPress eingestellt. Das bedeutet, dass dein Plugin in der Suche nicht auftaucht, da es erforderlich ist, dass Plugins bis zur neuesten Version von WordPress kompatibel und als getestet dokumentiert sind. | [Dokumentation](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/#readme-header-information) |
| 0 | 0 | ERROR | readme_short_description_non_official_language | Die Kurzbeschreibung in der Readme-Datei enthält inoffizielle Sprache. Sie muss in Standard-Englisch verfasst sein. | [Dokumentation](https://make.wordpress.org/plugins/2025/07/28/requiring-the-readme-to-be-written-in-english/) |
| 0 | 0 | ERROR | readme_description_non_official_language | Die Beschreibung in der Readme-Datei enthält inoffizielle Formulierungen. Sie muss in Standard-Englisch verfasst sein. | [Dokumentation](https://make.wordpress.org/plugins/2025/07/28/requiring-the-readme-to-be-written-in-english/) |

## `includes/class-plugin.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 35 | 3 | WARNING | PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound | load_plugin_textdomain() has been discouraged since WordPress version 4.6. Wenn dein Plugin auf WordPress.org gehostet wird, musst du diesen Funktionsaufruf für Übersetzungen nicht mehr manuell unter deiner Plugin-Titelform einfügen. WordPress wird die Übersetzungen bei Bedarf automatisch für dich laden. | [Dokumentation](https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/) |

## `uninstall.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 28 | 244 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: &quot;$option&quot;. |  |
