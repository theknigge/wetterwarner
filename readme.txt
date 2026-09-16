=== Wetterwarner ===
Contributors: bocanegra
Donate link: https://it93.de/unterstuetzen/
Tags: Wetter, Unwetter, Wetterwarnung, DWD, Block
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Amtliche Wetterwarnungen des Deutschen Wetterdienstes für Deine Region – als Block, Shortcode oder Widget.

== Description ==

Wetterwarner zeigt die amtlichen Wetterwarnungen des Deutschen Wetterdienstes (DWD) für eine frei wählbare Warnregion an – vom Landkreis bis zur einzelnen Gemeinde.

= Funktionen =

* **Block** für den Block-Editor und Block-Widget-Bereiche, mit Live-Vorschau
* **Shortcode** `[wetterwarner]` für Page-Builder und klassische Themes
* **Klassisches Widget** für ältere Themes
* Über 11.000 Warnregionen des DWD (Landkreise, Gemeinden, Küsten, Binnenseen) mit komfortabler Suche
* Farbliche Kennzeichnung nach Warnstufe, Icons, Gültigkeitszeitraum und aufklappbare Details mit Handlungsempfehlungen
* Vorabinformationen werden gekennzeichnet
* Optional: aktuelle Warnkarte des DWD für Deutschland oder ein Bundesland
* Zwischenspeicher und Aktualisierung im Hintergrund – schnelle Ladezeiten, schonend für den DWD
* Datenschutzfreundlich: Besucher laden keine Inhalte von externen Servern, auch die Warnkarte wird lokal ausgeliefert
* Keine Abhängigkeit zu jQuery oder Icon-Fonts im Frontend

[Live Demo](https://it93.de/projekte/wetterwarner/demo)

= Shortcode =

`[wetterwarner region="103241000" map="60"]`

Mögliche Attribute:

* `region` – Warncell-ID der Region (Pflicht) oder `demo` für Beispielmeldungen
* `title`, `intro`, `no_warnings` – Texte, `%region%` wird durch den Regionsnamen ersetzt
* `max` – maximale Anzahl Warnungen (Standard 3, 0 = alle)
* `show_always` – auch ohne Warnungen anzeigen (1/0)
* `validity`, `details`, `icons`, `colors`, `link`, `hide_duplicates` – Anzeigeoptionen (1/0)
* `map` – Breite der Warnkarte in Prozent (0 = keine Karte)
* `map_region` – `auto`, `de`, `baw`, `bay`, `bbb`, `hes`, `mvp`, `nib`, `nrw`, `rps`, `sac`, `saa`, `shh`, `thu`

Die Warncell-ID findest Du über die Regionssuche im Block oder Widget.

= Externe Dienste =

Die Abrufe erfolgen ausschließlich serverseitig durch Deine WordPress-Installation – höchstens alle 5 Minuten, alle genutzten Regionen gemeinsam in einer Anfrage. Besucher Deiner Webseite bauen keine Verbindung zu externen Servern auf.

**Wetterwarner-API (api.wetterwarner.de)** – Primäre Datenquelle. Die API speichert die Warnungen des Deutschen Wetterdienstes zentral zwischen, damit nicht jede Webseite die teils mehrere Megabyte großen DWD-Daten laden muss. Übertragen werden die Warncell-IDs der genutzten Regionen sowie technisch bedingt die IP-Adresse Deines Webservers; es werden keine Daten Deiner Besucher übertragen.
Anbieter: Tim Knigge, IT93. [Datenschutz](https://wetterwarner.de/datenschutz)

* `https://api.wetterwarner.de/v3/warnings` – Warnungen der genutzten Regionen
* `https://api.wetterwarner.de/v3/regions` – Regionssuche im Block- und Widget-Editor (übertragen wird nur der Suchbegriff)
* `https://api.wetterwarner.de/v3/maps/` – Warnkarten (nur wenn eine Karte angezeigt wird)

**Optional: Entwicklung unterstützen** – Nur wenn Du ausdrücklich zustimmst (Hinweis im Backend oder „Einstellungen > Wetterwarner“), werden beim regulären Abruf zusätzlich die Adresse Deiner Website sowie die Plugin-, WordPress- und PHP-Version übermittelt. Daten Deiner Besucher werden nicht übertragen. Die Zustimmung kann jederzeit widerrufen werden; die gespeicherten Angaben werden dann gelöscht. Einträge ohne Kontakt werden nach 90 Tagen automatisch entfernt.

**Deutscher Wetterdienst** – Nur als Rückfall, falls die Wetterwarner-API nicht erreichbar ist.

* `https://www.dwd.de/DWD/warnungen/warnapp/json/warnings.json` – Warnungen für Landkreise, Kreisteile, Küsten und Binnenseen
* `https://maps.dwd.de/geoserver/dwd/ows` – Warnungen für Gemeinden (Geodienst, gefiltert nach Warncell-ID)
* `https://www.dwd.de/DWD/warnungen/warnapp_gemeinden/json/` – Warnkarten

Die API-Adresse lässt sich über die Konstante `WETTERWARNER_API_URL` oder den Filter `wetterwarner_api_url` ändern; ein leerer Wert lädt ausschließlich direkt beim DWD.

Anbieter der Wetterdaten: Deutscher Wetterdienst, Frankfurter Straße 135, 63067 Offenbach. [Nutzungsbedingungen/Copyright](https://www.dwd.de/DE/service/copyright/copyright_node.html), [Datenschutz](https://www.dwd.de/DE/service/datenschutz/datenschutz_node.html), [Informationen zur Objekteinbindung](https://www.dwd.de/DE/wetter/warnungen_aktuell/objekt_einbindung/objekteinbindung.html).

= Wichtige Hinweise =

Dieses Plugin ersetzt keine amtliche Informationsquelle. Der DWD übernimmt keine Gewähr für die Verfügbarkeit der Daten; Pfade und Formate können sich ändern. Sämtliche Rechte an Warnungen und Karten liegen beim Deutschen Wetterdienst, die Karten werden nicht inhaltlich verändert.

Das Plugin wurde nach bestem Wissen und Gewissen erstellt und getestet. Nur für die Nutzung in Deutschland vorgesehen. Dieses Plugin steht in keiner Verbindung mit der gleichnamigen Android/iOS App.

= Credits =

* Icons von [Lucide](https://lucide.dev) (ISC-Lizenz, Warndreieck aus Feather unter MIT-Lizenz) – Lizenztexte in `licenses/lucide.txt`
* Warnungen, Warnkarten und Warnregionen: Deutscher Wetterdienst, [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/deed.de)
* [wp-color-picker-alpha](https://github.com/kallookoo/wp-color-picker-alpha) (GPLv2)

== Installation ==

1. Installiere das Plugin über "Plugins > Installieren" oder lade den Ordner nach `/wp-content/plugins/` hoch.
2. Aktiviere das Plugin.
3. Füge den Block "Wetterwarner" auf einer Seite oder in einem Widget-Bereich ein und wähle Deine Warnregion.

Farben je Warnstufe findest Du unter "Einstellungen > Wetterwarner".

== Frequently Asked Questions ==

= Wie finde ich meine Warnregion? =
Tippe im Block oder Widget einfach den Namen Deines Ortes oder Landkreises ein. Die Suche kennt alle Warnregionen des DWD.

= Landkreis oder Gemeinde – was soll ich wählen? =
Gemeinde-Warnungen sind genauer. Landkreis-Warnungen fassen alle Warnungen innerhalb des Kreises zusammen.

= Wie kann ich meine Einstellungen testen? =
Suche nach "demo" und wähle die Beispielregion. Es werden Beispielmeldungen aller Warnstufen angezeigt.

= Ich habe von Version 2.x aktualisiert. Was muss ich tun? =
Deine Widgets werden automatisch übernommen. Die alten wettwarn.de Feed-IDs werden dabei der passenden DWD-Warnregion zugeordnet. Falls das für eine ID nicht möglich ist, erscheint ein Hinweis im Backend – wähle die Region dann einfach neu aus.

= Die Warnungen aktualisieren sich nicht =
Verwendest Du ein Caching-Plugin, wird die Seite eventuell länger zwischengespeichert. Unter "Einstellungen > Wetterwarner" siehst Du, wann die Daten zuletzt geladen wurden.

= Wie erreiche ich den Entwickler? | Fehler melden =
Nutze das WordPress [Support Forum](https://wordpress.org/support/plugin/wetterwarner/) oder das [Kontaktformular](https://it93.de/kontakt/).

== Screenshots ==
1. Wetterwarner im Frontend
2. Block mit Regionssuche im Editor
3. Einstellungen

== Upgrade Notice ==

= 3.0.0 =
Großes Update: Neue Datenquelle direkt vom Deutschen Wetterdienst, Block und Shortcode. Benötigt WordPress 6.3. Bestehende Widgets werden automatisch übernommen – bitte nach dem Update kurz prüfen.

== Changelog ==

= 3.0.0 =
* Neu: Block "Wetterwarner" mit Live-Vorschau im Editor
* Neu: Shortcode `[wetterwarner]`
* Neu: Datenquelle direkt vom Deutschen Wetterdienst (statt wettwarn.de RSS)
* Neu: Auswahl aus über 11.000 DWD-Warnregionen inklusive Gemeinden, mit Suchfunktion
* Neu: Aufklappbare Details mit Beschreibung und Handlungsempfehlungen (ersetzt Tooltip)
* Neu: Aktuelle Gemeinde-Warnkarten des DWD, automatische Bundesland-Auswahl
* Neu: Warnkarte wird im Upload-Verzeichnis zwischengespeichert – kein Schreibzugriff auf den Plugin-Ordner mehr nötig
* Neu: Datenstatus und "Cache leeren" in den Einstellungen
* Optimierung: Keine Font-Awesome-, Icon-Font- oder jQuery-Abhängigkeit mehr im Frontend
* Optimierung: Automatische Übernahme bestehender Widgets aus Version 2.x
* Optimierung: Barrierefreiere Ausgabe (Überschriften, Screenreader-Texte, native Details)
* Mindestanforderung: WordPress 6.3

= 2.8.1 =
* Bugfix: Meldung Hintergrundfarben
* Kompatibilität zu WordPress 7.0 sichergestellt

= 2.8 =
* Wartungs- und Sicherheitsupdate
* Kompatibilität zu WordPress 6.8 & 6.9 (getestet mit 6.9-RC2) sichergestellt

= 2.7.3 =
* Wartungsupdate
* Kompatibilität zu WordPress 6.7 sichergestellt

= 2.7.2 =
* Bugfix: Daten nur sporadisch aktualisiert
* Bugfix: Mehrfach geplante Hintergrund-Aufgaben

= 2.7.1 =
* Bugfix: Falsche Verlinkung der Meldungen behoben
* Bugfix: Widget Checkbox-Einstellungen wurden unter umständen falsch geladen

= 2.7 =
* Optimierung: Benötigte externe Daten werden nun automatisch im Hintergrund aktualisiert (WP-Cron)
* Bugfix: Fehlermeldung nach Update behoben
* Weitere Quellcode Optimierungen
