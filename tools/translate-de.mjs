/**
 * Erzeugt languages/wetterwarner-de_DE.po aus der POT-Datei.
 * Danach: wp i18n make-mo languages && wp i18n make-json languages --no-purge
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = join( dirname( fileURLToPath( import.meta.url ) ), '../languages' );

const de = {
	Colors: 'Farben',
	'Background color level %d': 'Hintergrundfarbe Warnstufe %d',
	'These colors apply to all blocks, shortcodes and widgets when "Color by warning level" is enabled.':
		'Diese Farben gelten für alle Blöcke, Shortcodes und Widgets, bei denen „Nach Warnstufe einfärben“ aktiviert ist.',
	'Cache cleared.': 'Cache geleert.',
	Usage: 'Verwendung',
	'Add the "Wetterwarner" block in the block editor or in a widget area. Alternatively use the shortcode:':
		'Füge den Block „Wetterwarner“ im Block-Editor oder in einem Widget-Bereich ein. Alternativ kannst Du den Shortcode verwenden:',
	'All shortcode attributes': 'Alle Shortcode-Attribute',
	'Warncell ID of the region (required), or "demo"': 'Warncell-ID der Region (Pflicht) oder „demo“',
	'Texts, %region% is replaced': 'Texte, %region% wird ersetzt',
	'Maximum number of alerts (default 3, 0 = all)': 'Maximale Anzahl Warnungen (Standard 3, 0 = alle)',
	'Options: 1 or 0': 'Optionen: 1 oder 0',
	'Map width in percent (0 = no map)': 'Kartenbreite in Prozent (0 = keine Karte)',
	'Data status': 'Datenstatus',
	'Districts (warnings.json)': 'Landkreise (warnings.json)',
	'Municipalities (DWD geo service)': 'Gemeinden (DWD-Geodienst)',
	'Last update %s ago': 'Zuletzt aktualisiert vor %s',
	'Not loaded yet': 'Noch nicht geladen',
	'Regions in use': 'Genutzte Regionen',
	'Clear cache': 'Cache leeren',
	'Sorry, you are not allowed to do that.': 'Du bist leider nicht berechtigt, das zu tun.',
	'Weather alerts now come directly from the Deutscher Wetterdienst. The following old feed IDs could not be assigned to a warning region automatically: %s. Please select the region again in the widget settings.':
		'Die Wetterwarnungen kommen jetzt direkt vom Deutschen Wetterdienst. Folgende alte Feed-IDs konnten nicht automatisch einer Warnregion zugeordnet werden: %s. Bitte wähle die Region in den Widget-Einstellungen neu aus.',
	'Open widgets': 'Widgets öffnen',
	Dismiss: 'Ausblenden',
	Settings: 'Einstellungen',
	'Wetterwarner receives current weather alerts': 'Wetterwarner empfängt aktuelle Wetterwarnungen',
	'The alerts of the Deutscher Wetterdienst are loaded and cached successfully.':
		'Die Warnungen des Deutschen Wetterdienstes werden erfolgreich geladen und zwischengespeichert.',
	'Wetterwarner cannot store warning maps': 'Wetterwarner kann keine Warnkarten speichern',
	'The uploads directory is not writable. Warning maps cannot be displayed.':
		'Das Upload-Verzeichnis ist nicht beschreibbar. Warnkarten können nicht angezeigt werden.',
	'Wetterwarner cannot reach the Deutscher Wetterdienst': 'Wetterwarner erreicht den Deutschen Wetterdienst nicht',
	'Map directory writable': 'Kartenverzeichnis beschreibbar',
	'Next background update': 'Nächste Hintergrundaktualisierung',
	'Federal state of the region': 'Bundesland der Region',
	Germany: 'Deutschland',
	'Every 5 minutes': 'Alle 5 Minuten',
	'Sample town (demo)': 'Musterstadt (Demo)',
	District: 'Landkreis',
	'District area': 'Kreisteil',
	Municipality: 'Gemeinde',
	'Inland lake': 'Binnensee',
	Coast: 'Küste',
	'Weather alerts': 'Wetterwarnungen',
	'Weather alerts for %region%': 'Wetterwarnungen für %region%',
	'No weather alerts for %region%': 'Keine Wetterwarnungen für %region%',
	'Please select a warning region in the Wetterwarner settings.': 'Bitte wähle in den Wetterwarner-Einstellungen eine Warnregion aus.',
	'There are currently no warnings. The block is hidden on the website until a warning is issued (option "Show always").':
		'Aktuell liegen keine Warnungen vor. Auf der Webseite bleibt der Block ausgeblendet, bis eine Warnung vorliegt (Option „Auch ohne Warnungen anzeigen“).',
	'Weather alerts are currently unavailable.': 'Wetterwarnungen sind derzeit nicht verfügbar.',
	'View alerts at dwd.de': 'Warnungen auf dwd.de ansehen',
	'Source: %s': 'Quelle: %s',
	'Warning level %d': 'Warnstufe %d',
	'Prior information': 'Vorabinformation',
	'D, m/d, g:i a': 'D, d.m., H:i',
	'From %s until further notice': 'Ab %s bis auf Weiteres',
	'Until further notice': 'Bis auf Weiteres',
	'g:i a': 'H:i \\U\\h\\r',
	'between %1$d m and %2$d m': 'zwischen %1$d m und %2$d m',
	'above %d m': 'oberhalb %d m',
	'below %d m': 'unterhalb %d m',
	'Current weather warning map: %s': 'Aktuelle Warnkarte: %s',
	'Warning region not found.': 'Warnregion nicht gefunden.',
	'Invalid warning region.': 'Ungültige Warnregion.',
	'The DWD response could not be read.': 'Die Antwort des DWD konnte nicht gelesen werden.',
	'DWD could not be reached: %s': 'Der DWD ist nicht erreichbar: %s',
	'DWD responded with HTTP status %d.': 'Der DWD antwortete mit HTTP-Status %d.',
	'Displays official weather alerts of the Deutscher Wetterdienst and, if desired, a warning map.':
		'Zeigt amtliche Wetterwarnungen des Deutschen Wetterdienstes und auf Wunsch eine Warnkarte an.',
	'Warning region': 'Warnregion',
	'Search town or district …': 'Ort oder Landkreis suchen …',
	'Enter at least 2 characters. Search "demo" for sample warnings.': 'Mindestens 2 Zeichen eingeben. Mit „demo“ erhältst Du Beispielmeldungen.',
	Title: 'Titel',
	'Introduction text': 'Einleitungstext',
	'Text when there are no alerts': 'Text ohne Warnungen',
	'%region% is replaced by the name of the region.': '%region% wird durch den Namen der Region ersetzt.',
	'Maximum number of alerts (0 = all)': 'Maximale Anzahl Warnungen (0 = alle)',
	'Show even without alerts': 'Auch ohne Warnungen anzeigen',
	'Show validity period': 'Gültigkeitszeitraum anzeigen',
	'Show expandable details': 'Aufklappbare Details anzeigen',
	'Show icons': 'Icons anzeigen',
	'Color by warning level': 'Nach Warnstufe einfärben',
	'Hide duplicate alerts': 'Doppelte Warnungen ausblenden',
	'Link alerts to dwd.de': 'Warnungen mit dwd.de verlinken',
	'Show source': 'Quelle anzeigen',
	'Map width in % (0 = no map)': 'Kartenbreite in % (0 = keine Karte)',
	'Map area': 'Kartenausschnitt',
	'Search for a town, district or warncell ID. Search "demo" for sample warnings.':
		'Suche nach Ort, Landkreis oder Warncell-ID. Mit „demo“ erhältst Du Beispielmeldungen.',
	Texts: 'Texte',
	'Title level': 'Überschriftenebene',
	'%region% is replaced by the name of the region. Empty fields are hidden.':
		'%region% wird durch den Namen der Region ersetzt. Leere Felder werden ausgeblendet.',
	Display: 'Anzeige',
	'Warning map': 'Warnkarte',
	'Select the warning region for which official alerts of the Deutscher Wetterdienst should be displayed.':
		'Wähle die Warnregion, für die amtliche Warnungen des Deutschen Wetterdienstes angezeigt werden sollen.',
	'Official weather alerts of the Deutscher Wetterdienst for a warning region.':
		'Amtliche Wetterwarnungen des Deutschen Wetterdienstes für eine Warnregion.',
};

const unquote = ( s ) => JSON.parse( s ); // PO-Strings sind kompatibel zu JSON-Strings.
const quote = ( s ) => JSON.stringify( s );

const blocks = readFileSync( join( dir, 'wetterwarner.pot' ), 'utf8' ).split( /\r?\n\r?\n/ );
const missing = [];

const out = blocks.map( ( block, i ) => {
	const lines = block.split( /\r?\n/ );
	if ( i === 0 ) {
		return block
			.replace( /"Language-Team: .*?\\n"/, '"Language-Team: German\\n"' )
			.replace( /"Content-Type:/, '"Language: de_DE\\n"\n"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n"Content-Type:' );
	}
	const start = lines.findIndex( ( l ) => l.startsWith( 'msgid ' ) );
	const end = lines.findIndex( ( l ) => l.startsWith( 'msgstr ' ) );
	if ( start < 0 || end < 0 ) {
		return block;
	}
	const msgid = [ lines[ start ].slice( 6 ), ...lines.slice( start + 1, end ) ].map( unquote ).join( '' );
	const translation = de[ msgid ];
	if ( translation === undefined ) {
		missing.push( msgid );
		return block;
	}
	return [ ...lines.slice( 0, end ), `msgstr ${ quote( translation ) }` ].join( '\n' );
} );

writeFileSync( join( dir, 'wetterwarner-de_DE.po' ), out.join( '\n\n' ) );
console.log( 'Nicht übersetzt (bleiben unverändert):', missing );
