<?php
/**
 * Prüft die Übernahme der Widget-Einstellungen aus Version 2.x:
 *   wp eval-file wp-content/plugins/wetterwarner/tools/migration-test.php
 *
 * @package Wetterwarner
 */

use Wetterwarner\Plugin;
use Wetterwarner\Regions;
use Wetterwarner\Renderer;

$check = static function ( $label, $ok, $detail = '' ) {
	WP_CLI::log( ( $ok ? '  OK   ' : '  FAIL ' ) . str_pad( $label, 46 ) . ( is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE ) ) );
};

// Ausgangslage: Bestand wie unter 2.x, ohne Versionsmarke.
$old = array(
	// Vollständig konfiguriertes Widget, Feed-ID klein geschrieben.
	2  => array(
		'title'                  => 'unwetter',
		'ww_widget_titel'        => 'Unwetter in %region%',
		'ww_feed_id'             => 'han',
		'ww_einleitungstext'     => 'Warnungen für %region%',
		'ww_hinweistext'         => 'Alles ruhig in %region%',
		'ww_text_feed'           => 'Feed für %region%',
		'ww_max_meldungen'       => '5',
		'ww_kartengroesse'       => '65',
		'ww_kartenbundesland'    => 'Niedersachsen',
		'ww_kartenbundeslandURL' => 'warning_map_nib.webp',
		'ww_icons_zeigen'        => 1,
		'ww_hintergrundfarbe'    => 1,
		'ww_gueltigkeit_zeigen'  => 1,
		'ww_tooltip_zeigen'      => 1,
		'ww_meldungen_verlinken' => 1,
		'ww_doppelte_ausblenden' => 1,
		'ww_immer_zeigen'        => 1,
		'ww_feed_zeigen'         => 1,
	),
	// Minimal-Widget: nur Feed-ID, alle Checkboxen fehlen (2.x speicherte sie dann nicht).
	3  => array(
		'ww_feed_id'          => 'HHX',
		'ww_max_meldungen'    => '3',
		'ww_kartengroesse'    => '0',
		'ww_kartenbundesland' => 'Hamburg',
	),
	// Unbekannte Feed-ID.
	4  => array( 'ww_feed_id' => 'zzz', 'ww_max_meldungen' => '2' ),
	// Test-Feed der alten Version.
	5  => array( 'ww_feed_id' => '100', 'ww_immer_zeigen' => 1 ),
	// Alter Stand (2.4) mit voller Karten-URL.
	6  => array(
		'ww_feed_id'             => 'MXX',
		'ww_kartengroesse'       => '80',
		'ww_kartenbundesland'    => 'Bayern',
		'ww_kartenbundeslandURL' => 'https://api.it93.de/wetterwarner/worker/files/warning_map_bay.webp',
	),
	'_multiwidget' => 1,
);

update_option( 'widget_wetterwarner_widget', $old );
update_option( 'wetterwarner_settings', array( 'ww_farbe_stufe1' => 'rgba(1,2,3,0.4)' ) );
update_option(
	'sidebars_widgets',
	array(
		'sidebar-1'          => array( 'wetterwarner_widget-2', 'wetterwarner_widget-5' ),
		'wp_inactive_widgets' => array( 'wetterwarner_widget-3' ),
		'array_version'      => 3,
	)
);
delete_option( 'wetterwarner_version' );
delete_option( Plugin::MIGRATION_OPTION );
delete_option( 'wetterwarner_regions' );

// Update ausführen (läuft im Plugin auf "init").
Plugin::maybe_upgrade();
$new = get_option( 'widget_wetterwarner_widget' );

$check( 'Version markiert', WETTERWARNER_VERSION === get_option( 'wetterwarner_version' ), get_option( 'wetterwarner_version' ) );
$check( 'Alle Widgets erhalten', array_keys( $old ) === array_keys( $new ), array_keys( $new ) );
$check( 'Widget-Zuordnung unverändert', in_array( 'wetterwarner_widget-2', get_option( 'sidebars_widgets' )['sidebar-1'], true ), get_option( 'sidebars_widgets' )['sidebar-1'] );

// Widget 2: alle Felder.
$w = $new[2];
$check( 'han → Region Hannover', '103241000' === $w['regionId'], $w['regionId'] );
$check( 'Titel übernommen', 'Unwetter in %region%' === $w['title'], $w['title'] );
$check( 'Einleitungstext übernommen', 'Warnungen für %region%' === $w['introText'], $w['introText'] );
$check( 'Hinweistext übernommen', 'Alles ruhig in %region%' === $w['noWarningsText'], $w['noWarningsText'] );
$check( 'Max. Meldungen übernommen', 5 === $w['maxWarnings'], $w['maxWarnings'] );
$check( 'Kartengröße übernommen', 65 === $w['mapSize'], $w['mapSize'] );
$check( 'Niedersachsen → nib', 'nib' === $w['mapRegion'], $w['mapRegion'] );
$check(
	'Alle Häkchen übernommen',
	$w['showIcons'] && $w['showColors'] && $w['showValidity'] && $w['showDetails'] && $w['linkWarnings'] && $w['hideDuplicates'] && $w['showAlways'],
	array_intersect_key( $w, array_flip( array( 'showIcons', 'showColors', 'showValidity', 'showDetails', 'linkWarnings', 'hideDuplicates', 'showAlways' ) ) )
);
$check( 'Keine alten Schlüssel mehr', ! array_intersect( array_keys( $w ), array( 'ww_feed_id', 'ww_widget_titel', 'ww_kartenbundeslandURL' ) ), array_keys( $w ) );

// Widget 3: fehlende Checkboxen werden zu "aus", Standardtexte greifen.
$w3 = $new[3];
$check( 'HHX → Hansestadt Hamburg', '102000000' === $w3['regionId'], $w3['regionId'] );
$check( 'Fehlende Häkchen = aus', ! $w3['showIcons'] && ! $w3['showAlways'], array( $w3['showIcons'], $w3['showAlways'] ) );
$check( 'Hamburg → shh', 'shh' === $w3['mapRegion'], $w3['mapRegion'] );

// Widget 4 und 5.
$check( 'Unbekannte ID leer + Hinweis', '' === $new[4]['regionId'] && in_array( 'ZZZ', (array) get_option( Plugin::MIGRATION_OPTION ), true ), get_option( Plugin::MIGRATION_OPTION ) );
$check( '100 → Demo-Region', Regions::DEMO === $new[5]['regionId'], $new[5]['regionId'] );
$check( 'MXX → Kreis und Stadt München', '909184999' === $new[6]['regionId'], $new[6]['regionId'] );
$check( 'Bayern → bay', 'bay' === $new[6]['mapRegion'], $new[6]['mapRegion'] );

// Farben der alten Einstellungsseite bleiben.
$check( 'Farbeinstellung erhalten', 'rgba(1,2,3,0.4)' === get_option( 'wetterwarner_settings' )['ww_farbe_stufe1'], get_option( 'wetterwarner_settings' ) );

// Zweiter Durchlauf darf nichts zerstören.
$snapshot = $new;
Plugin::maybe_upgrade();
$check( 'Erneuter Aufruf ändert nichts', $snapshot === get_option( 'widget_wetterwarner_widget' ), 'unverändert' );

// Ausgabe im Frontend.
$html = Renderer::render( $new[2] );
$check( 'Widget 2 rendert', false !== strpos( $html, 'wetterwarner' ) && '' !== $html, wp_strip_all_tags( substr( $html, 0, 120 ) ) );
$check( 'Region im Text ersetzt', false === strpos( $html, '%region%' ), false !== strpos( $html, 'Hannover' ) ? 'Name "Hannover" enthalten' : wp_strip_all_tags( substr( $html, 0, 160 ) ) );
