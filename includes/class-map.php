<?php
/**
 * Warnkarten des DWD. Die Bilder werden lokal im Upload-Verzeichnis
 * zwischengespeichert, damit Besucher keine externe Verbindung aufbauen.
 * Quelle ist ausschließlich die Wetterwarner-API (mit Supporter-Lizenz bei
 * Ausfall des Hauptservers der Fallback-Server).
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

defined( 'ABSPATH' ) || exit;

class Map {

	/** Cron aktualisiert Karten, die älter sind (Sekunden). */
	const REFRESH_AGE = 270;

	/** Ohne Cron: beim Seitenaufruf erst ab diesem Alter nachladen (Sekunden). */
	const LAZY_AGE = 3600;

	/** Bundesland (Kürzel der Warncell-Liste) => DWD-Kartencode. */
	const STATE_CODES = array(
		'SH' => 'shh',
		'HH' => 'shh',
		'NI' => 'nib',
		'HB' => 'nib',
		'NW' => 'nrw',
		'HE' => 'hes',
		'RP' => 'rps',
		'SL' => 'rps',
		'BW' => 'baw',
		'BY' => 'bay',
		'BE' => 'bbb',
		'BB' => 'bbb',
		'MV' => 'mvp',
		'SN' => 'sac',
		'ST' => 'saa',
		'TH' => 'thu',
	);

	/**
	 * Auswahlmöglichkeiten für die Karte.
	 *
	 * @return array<string, string>
	 */
	public static function choices() {
		return array(
			'auto' => __( 'Federal state of the region', 'wetterwarner' ),
			'de'   => __( 'Germany', 'wetterwarner' ),
			'baw'  => 'Baden-Württemberg',
			'bay'  => 'Bayern',
			'bbb'  => 'Berlin / Brandenburg',
			'hes'  => 'Hessen',
			'mvp'  => 'Mecklenburg-Vorpommern',
			'nib'  => 'Niedersachsen / Bremen',
			'nrw'  => 'Nordrhein-Westfalen',
			'rps'  => 'Rheinland-Pfalz / Saarland',
			'sac'  => 'Sachsen',
			'saa'  => 'Sachsen-Anhalt',
			'shh'  => 'Schleswig-Holstein / Hamburg',
			'thu'  => 'Thüringen',
		);
	}

	/**
	 * Kartencode für eine Einstellung und Region.
	 *
	 * @param string     $setting "auto", "de" oder Kartencode.
	 * @param array|null $region  Region aus Regions::get().
	 */
	public static function resolve_code( $setting, $region ) {
		if ( 'auto' === $setting ) {
			$state = $region && ! empty( $region['state'] ) ? $region['state'] : '';
			return isset( self::STATE_CODES[ $state ] ) ? self::STATE_CODES[ $state ] : 'de';
		}
		return array_key_exists( $setting, self::choices() ) ? $setting : 'de';
	}

	/**
	 * Öffentliche URL der lokal gespeicherten Karte.
	 *
	 * @param string $code Kartencode.
	 * @return array{url:string,width:int,height:int}|null
	 */
	public static function get( $code ) {
		$file = self::find_file( $code );

		if ( ! $file || time() - filemtime( $file ) > self::LAZY_AGE ) {
			// Nur wenn die Karte fehlt oder der Cron offensichtlich nicht läuft.
			self::download( $code );
			$file = self::find_file( $code );
		}
		// Keine Rückfallebene: eine veraltete Warnkarte lieber ausblenden als falsch anzeigen.
		if ( ! $file || time() - filemtime( $file ) > Source::MAX_STALE ) {
			return null;
		}

		$size = wp_getimagesize( $file );
		$dir  = self::upload_dir();

		return array(
			'url'    => add_query_arg( 'ver', filemtime( $file ), $dir['url'] . '/' . basename( $file ) ),
			'width'  => $size ? (int) $size[0] : 0,
			'height' => $size ? (int) $size[1] : 0,
		);
	}

	/**
	 * WP-Cron: Karte laden, sofern sie älter als REFRESH_AGE ist.
	 *
	 * @param string $code Kartencode.
	 */
	public static function refresh( $code ) {
		$file = self::find_file( $code );
		if ( ! $file || time() - filemtime( $file ) > self::REFRESH_AGE ) {
			self::download( $code );
		}
	}

	/**
	 * Entfernt alle lokal gespeicherten Karten.
	 */
	public static function clear_cache() {
		$dir = self::upload_dir();
		foreach ( array( 'png', 'webp' ) as $ext ) {
			foreach ( (array) glob( $dir['path'] . '/map-*.' . $ext ) as $file ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * @return array{path:string,url:string}
	 */
	public static function upload_dir() {
		$uploads = wp_upload_dir( null, false );
		return array(
			'path' => $uploads['basedir'] . '/wetterwarner',
			'url'  => set_url_scheme( $uploads['baseurl'] . '/wetterwarner' ),
		);
	}

	private static function is_code( $code ) {
		return 'auto' !== $code && array_key_exists( $code, self::choices() );
	}

	private static function find_file( $code ) {
		if ( ! self::is_code( $code ) ) {
			return null;
		}
		$dir = self::upload_dir();
		foreach ( array( 'webp', 'png' ) as $ext ) {
			$file = $dir['path'] . '/map-' . $code . '.' . $ext;
			if ( is_file( $file ) ) {
				return $file;
			}
		}
		return null;
	}

	private static function download( $code ) {
		if ( ! self::is_code( $code ) ) {
			return false;
		}
		$dir = self::upload_dir();
		if ( ! wp_mkdir_p( $dir['path'] ) ) {
			return false;
		}

		$path = 'maps/' . $code . '.webp';
		$base = Source::api_url();
		$body = '' !== $base ? self::fetch( trailingslashit( $base ) . $path ) : '';

		// Hauptserver nicht erreichbar: mit Supporter-Lizenz beim Fallback-Server versuchen
		// (der erkennt die Lizenz am Header und lehnt Websites ohne Lizenz mit HTTP 503 ab).
		if ( ! self::is_webp( $body ) && '' !== $base && Source::license()['active'] ) {
			$fallback = Source::fallback_api_url();
			if ( '' !== $fallback ) {
				$body = self::fetch( trailingslashit( $fallback ) . $path, array( 'X-Wetterwarner-License-Site' => Telemetry::site() ) );
			}
		}

		if ( self::is_webp( $body ) ) {
			return self::store( $dir['path'] . '/map-' . $code . '.webp', $body, $dir['path'] . '/map-' . $code . '.png' );
		}
		return false;
	}

	private static function is_webp( $body ) {
		return $body && 'RIFF' === substr( $body, 0, 4 ) && 'WEBP' === substr( $body, 8, 4 );
	}

	private static function fetch( $url, $headers = array() ) {
		$response = wp_remote_get(
			$url,
			array(
				'headers'    => $headers,
				'timeout'    => 15,
				'user-agent' => 'Wetterwarner/' . WETTERWARNER_VERSION . ' (WordPress; +https://wordpress.org/plugins/wetterwarner/)',
			)
		);
		return is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ? '' : wp_remote_retrieve_body( $response );
	}

	private static function store( $file, $body, $remove ) {
		if ( false === file_put_contents( $file . '.tmp', $body ) || ! rename( $file . '.tmp', $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}
		if ( is_file( $remove ) ) {
			wp_delete_file( $remove );
		}
		return true;
	}
}
