<?php
/**
 * Schnelltest per WP-CLI:  wp eval-file wp-content/plugins/wetterwarner/tools/smoke-test.php
 *
 * @package Wetterwarner
 */

use Wetterwarner\Regions;
use Wetterwarner\Renderer;
use Wetterwarner\Source;

$out = static function ( $label, $value ) {
	WP_CLI::log( str_pad( $label, 28 ) . ( is_string( $value ) ? $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) ) );
};

// 1) Migration aus 2.x.
$out( 'Version option', get_option( 'wetterwarner_version' ) );
$out( 'Migrated widgets', get_option( 'widget_wetterwarner_widget' ) );
$out( 'Unmatched feed IDs', get_option( 'wetterwarner_migration' ) );

// 2) Suche.
foreach ( array( 'hannover', 'munchen', 'wildeshausen', '103241', 'demo' ) as $term ) {
	$out( "Search '$term'", array_slice( wp_list_pluck( Regions::search( $term, 5 ), 'name', 'id' ), 0, 5, true ) );
}

// 3) Kreisebene: erste Zelle mit Warnung aus dem Live-Feed.
$raw   = wp_remote_retrieve_body( wp_remote_get( Source::DISTRICT_URL ) );
$json  = json_decode( preg_replace( '/^\s*warnWetter\.loadWarnings\(|\);?\s*$/', '', $raw ), true );
$cells = array_keys( $json['warnings'] );
$out( 'Cells with warnings', count( $cells ) );

$district = null;
foreach ( $cells as $cell ) {
	if ( '1' === substr( $cell, 0, 1 ) && Regions::get( $cell ) ) {
		$district = (string) $cell;
		break;
	}
}
if ( $district ) {
	$w = Source::get_warnings( $district );
	$out( "District $district", is_wp_error( $w ) ? $w->get_error_message() : array_map( static function ( $x ) { return $x['event'] . ' stage ' . $x['stage'] . ' ' . $x['type']; }, $w ) );
}

// 4) Gemeinde: eine Gemeinde mit aktueller Warnung per WFS suchen.
$wfs  = add_query_arg(
	array(
		'service'      => 'WFS',
		'version'      => '2.0.0',
		'request'      => 'GetFeature',
		'typeName'     => 'dwd:Warnungen_Gemeinden',
		'outputFormat' => 'application/json',
		'propertyName' => 'WARNCELLID',
		'count'        => 1,
	),
	Source::WFS_URL
);
$feat = json_decode( wp_remote_retrieve_body( wp_remote_get( $wfs ) ), true );
$muni = ! empty( $feat['features'] ) ? (string) $feat['features'][0]['properties']['WARNCELLID'] : '801002000';
$w    = Source::get_warnings( $muni );
$out( "Municipality $muni", is_wp_error( $w ) ? $w->get_error_message() : array_map( static function ( $x ) { return $x['event'] . ' stage ' . $x['stage'] . ' prior ' . (int) $x['prior'] . ' ' . wp_date( 'd.m. H:i', $x['start'] ) . '-' . ( $x['end'] ? wp_date( 'H:i', $x['end'] ) : 'open' ); }, $w ) );

// 5) Shortcode/Renderer.
$html = do_shortcode( '[wetterwarner region="demo" max="0" map="50"]' );
$out( 'Demo HTML length', (string) strlen( $html ) );
$out( 'Demo items', (string) substr_count( $html, 'wetterwarner__item ' ) );
$out( 'Demo has map img', false !== strpos( $html, 'wetterwarner/map-nib' ) ? 'yes' : 'no: ' . substr( $html, -300 ) );
$out( 'Hidden w/o warnings', '' === Renderer::render( array( 'regionId' => '501000001' ) ) || Source::get_warnings( '501000001' ) ? 'ok' : 'unexpected' );
$out( 'Invalid region (guest)', Renderer::render( array( 'regionId' => 'x' ) ) );

// 6) Cron.
Wetterwarner\Plugin::refresh();
$out( 'Usage', Wetterwarner\Plugin::usage() );
$out( 'Status', Source::status() );
$out( 'Next cron', wp_next_scheduled( 'wetterwarner_refresh' ) ? 'scheduled' : 'MISSING' );

file_put_contents( WP_CONTENT_DIR . '/uploads/ww-demo.html', $html );
