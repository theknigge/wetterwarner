<?php
/**
 * Warnkarten des DWD. Die Bilder werden lokal im Upload-Verzeichnis
 * zwischengespeichert, damit Besucher keine Verbindung zum DWD aufbauen.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

defined( 'ABSPATH' ) || exit;

class Map {

	const SOURCE_URL = 'https://www.dwd.de/DWD/warnungen/warnapp_gemeinden/json/warnungen_gemeinde_map_%s.png';
	const MAX_AGE    = 900;

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

		if ( ! $file || time() - filemtime( $file ) > self::MAX_AGE * 4 ) {
			// Fehlt die Karte oder ist sie deutlich veraltet (Cron läuft nicht), sofort laden.
			self::download( $code );
			$file = self::find_file( $code );
		}
		if ( ! $file ) {
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
	 * Lädt eine Karte, sofern sie älter als MAX_AGE ist.
	 *
	 * @param string $code Kartencode.
	 * @param bool   $force Alter ignorieren.
	 */
	public static function refresh( $code, $force = false ) {
		$file = self::find_file( $code );
		if ( $force || ! $file || time() - filemtime( $file ) > self::MAX_AGE ) {
			self::download( $code );
		}
	}

	/**
	 * Entfernt alle lokal gespeicherten Karten.
	 */
	public static function clear_cache() {
		$dir = self::upload_dir();
		foreach ( (array) glob( $dir['path'] . '/map-*.{png,webp}', GLOB_BRACE ) as $file ) {
			wp_delete_file( $file );
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

	private static function find_file( $code ) {
		if ( ! array_key_exists( $code, self::choices() ) || 'auto' === $code ) {
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
		if ( ! array_key_exists( $code, self::choices() ) || 'auto' === $code ) {
			return false;
		}

		$dir = self::upload_dir();
		if ( ! wp_mkdir_p( $dir['path'] ) ) {
			return false;
		}

		$response = wp_remote_get(
			sprintf( self::SOURCE_URL, $code ),
			array(
				'timeout'    => 15,
				'user-agent' => 'Wetterwarner/' . WETTERWARNER_VERSION . ' (WordPress; +https://wordpress.org/plugins/wetterwarner/)',
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( 0 !== strpos( $body, "\x89PNG" ) ) {
			return false;
		}

		$png = $dir['path'] . '/map-' . $code . '.png';
		$tmp = $png . '.tmp';
		if ( false === file_put_contents( $tmp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return false;
		}

		// Wenn möglich als WebP speichern (deutlich kleiner). Der Bildinhalt bleibt unverändert.
		$editor = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? wp_get_image_editor( $tmp ) : null;
		if ( $editor && ! is_wp_error( $editor ) ) {
			$saved = $editor->save( $dir['path'] . '/map-' . $code . '.webp', 'image/webp' );
			if ( ! is_wp_error( $saved ) ) {
				wp_delete_file( $tmp );
				if ( is_file( $png ) ) {
					wp_delete_file( $png );
				}
				return true;
			}
		}

		return rename( $tmp, $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
	}
}
