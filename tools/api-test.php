<?php
/**
 * Integrationstest Plugin <-> Wetterwarner-API per WP-CLI:
 *   wp eval-file wp-content/plugins/wetterwarner/tools/api-test.php
 *
 * Erwartet WETTERWARNER_API_URL in wp-config.php.
 *
 * @package Wetterwarner
 */

use Wetterwarner\Map;
use Wetterwarner\Plugin;
use Wetterwarner\Source;

$check = static function ( $label, $ok, $detail = '' ) {
	WP_CLI::log( ( $ok ? '  OK   ' : '  FAIL ' ) . str_pad( $label, 52 ) . ( is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE ) ) );
};

// Abrufe mitzählen.
$requests = array();
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( &$requests ) {
		$requests[] = $url;
		return $pre;
	},
	10,
	3
);

WP_CLI::log( 'API: ' . Source::api_url() );
Source::clear_cache();
Map::clear_cache();
delete_option( Source::STATUS_OPTION );
update_option( Plugin::USAGE_OPTION, array( 'regions' => array( '103241000' => time(), '803241001' => time() ), 'maps' => array( 'nib' => time() ) ), false );

// 1) Cron-Refresh: ein Batch-Request für alle Regionen.
$requests = array();
$result   = Source::refresh( array( '103241000', '803241001' ) );
$api_hits = array_filter( $requests, static function ( $u ) { return false !== strpos( $u, '/warnings' ); } );
$check( 'Cron-Refresh erfolgreich', true === $result, $result );
$check( 'Genau 1 API-Request für 2 Regionen', 1 === count( $requests ) && 1 === count( $api_hits ), $requests );
$check( 'Status api ok', ! empty( Source::status()['api']['ok'] ), Source::status() );

// 2) Zweiter Refresh direkt danach wird übersprungen (5-Minuten-Limit).
$requests = array();
$check( 'Zweiter Refresh übersprungen', false === Source::refresh( array( '103241000', '803241001' ) ) && ! $requests, $requests );

// 3) Seitenaufruf liest nur lokal.
$requests = array();
$w        = Source::get_warnings( '103241000' );
$check( 'get_warnings ohne HTTP-Request', ! is_wp_error( $w ) && ! $requests, is_wp_error( $w ) ? $w->get_error_message() : count( $w ) . ' Warnungen' );

// 4) Neue Region: einmaliger Sofortabruf, danach gesperrt.
$requests = array();
$w        = Source::get_warnings( '109162000' );
$check( 'Neue Region sofort geladen (1 Request)', ! is_wp_error( $w ) && 1 === count( $requests ), $requests );
$store = Source::store();
unset( $store['cells']['101001000'], $store['fetched']['101001000'] );
$requests = array();
Source::refresh( array( '101001000' ), true );
Source::refresh( array( '101001000' ), true );
$check( 'Sofortabruf je Region max. 1x pro Minute', 1 === count( $requests ), $requests );

// 5) Cron nach Ablauf des Intervalls lädt wieder.
$store                 = get_option( Source::STORE_OPTION );
$store['last_request'] = time() - Source::REFRESH_INTERVAL;
update_option( Source::STORE_OPTION, $store, false );
$requests = array();
Plugin::refresh();
$warning_requests = array_filter( $requests, static function ( $u ) { return false !== strpos( $u, '/warnings' ); } );
$check( 'Cron nach 5 Min: 1 Warnungs-Request', 1 === count( $warning_requests ), $requests );

// 6) Karte kommt als WebP von der API.
$map = Map::get( 'nib' );
$check( 'Karte aus API (WebP)', $map && false !== strpos( $map['url'], 'map-nib.webp' ), $map );

// 7) API nicht erreichbar: kein Abruf beim DWD, gespeicherte Warnungen bleiben begrenzt sichtbar.
add_filter( 'wetterwarner_api_url', static function () { return 'http://127.0.0.1:9/v3/'; }, 99 );
$store                 = get_option( Source::STORE_OPTION );
$store['last_request'] = 0;
update_option( Source::STORE_OPTION, $store, false );
$requests = array();
$result   = Source::refresh( array( '103241000', '803241001' ) );
$status   = Source::status();
$dwd      = array_filter( $requests, static function ( $u ) { return false !== strpos( $u, 'dwd.de' ); } );
$check( 'API-Ausfall wird gemeldet', is_wp_error( $result ) && ! empty( $status['api']['error'] ), isset( $status['api']['error'] ) ? $status['api']['error'] : '' );
$check( 'Kein Abruf beim DWD', ! $dwd, $requests );
$check( 'Gespeicherte Warnungen bleiben sichtbar', ! is_wp_error( Source::get_warnings( '103241000' ) ), '' );

$store                         = get_option( Source::STORE_OPTION );
$store['fetched']['103241000'] = time() - Source::MAX_STALE - 60;
update_option( Source::STORE_OPTION, $store, false );
$stale = Source::get_warnings( '103241000' );
$check( 'Nach 2 Stunden: Hinweis statt alter Daten', is_wp_error( $stale ), is_wp_error( $stale ) ? $stale->get_error_code() : 'noch Daten' );

Map::clear_cache();
$requests = array();
$check( 'Karte ohne API: kein DWD-Download', null === Map::get( 'nib' ) && ! array_filter( $requests, static function ( $u ) { return false !== strpos( $u, 'dwd.de' ); } ), $requests );

$health = Wetterwarner\Admin::site_status_test();
$check( 'Website-Zustand: API-Ausfall = kritisch', 'critical' === $health['status'], $health['label'] );
