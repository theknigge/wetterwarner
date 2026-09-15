<?php
/**
 * Warnregionen (DWD Warncell-IDs). Die vollständige Liste liegt in der
 * Wetterwarner-API; das Plugin speichert nur die Angaben genutzter Regionen.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class Regions {

	/** Pseudo-Region mit Beispielmeldungen (ersetzt die alte Feed-ID "100"). */
	const DEMO = 'demo';

	/** Gespeicherte Angaben genutzter Regionen (id => name, state, type). */
	const OPTION = 'wetterwarner_regions';

	/** Zwischenspeicher für Suchergebnisse (Sekunden). */
	const SEARCH_CACHE = DAY_IN_SECONDS;

	/**
	 * Region als assoziatives Array: id, name, state, type.
	 *
	 * Reihenfolge: lokal gespeichert → einmaliger API-Abruf → neutraler Platzhalter.
	 *
	 * @param string|int $id Warncell-ID oder "demo".
	 */
	public static function get( $id ) {
		if ( self::DEMO === $id ) {
			return array(
				'id'    => self::DEMO,
				'name'  => __( 'Sample town (demo)', 'wetterwarner' ),
				'state' => 'NI',
				'type'  => 'k',
			);
		}
		if ( ! self::is_valid_id( $id ) ) {
			return null;
		}

		$id    = (string) $id;
		$known = (array) get_option( self::OPTION, array() );
		if ( isset( $known[ $id ] ) ) {
			return $known[ $id ];
		}

		// Höchstens alle 10 Minuten je Region nachfragen, falls die API nicht antwortet.
		$attempt = 'wetterwarner_region_try_' . $id;
		if ( ! get_transient( $attempt ) ) {
			set_transient( $attempt, 1, 10 * MINUTE_IN_SECONDS );
			$region = self::api_request( 'regions/' . $id );
			if ( ! is_wp_error( $region ) && self::is_region( $region ) && (string) $region['id'] === $id ) {
				self::remember( array( $region ) );
				return self::clean( $region );
			}
		}

		return array(
			'id'    => $id,
			/* translators: %s: warncell ID */
			'name'  => sprintf( __( 'Warning region %s', 'wetterwarner' ), $id ),
			'state' => '',
			'type'  => self::type_from_id( $id ),
		);
	}

	/**
	 * Speichert Regionsangaben aus API-Antworten (Warnungen, Suche, Einzelabruf).
	 *
	 * @param array $regions Liste oder Map von Regionen.
	 */
	public static function remember( array $regions ) {
		$known   = (array) get_option( self::OPTION, array() );
		$changed = false;

		foreach ( $regions as $region ) {
			if ( ! self::is_region( $region ) ) {
				continue;
			}
			$region = self::clean( $region );
			if ( ! isset( $known[ $region['id'] ] ) || $known[ $region['id'] ] !== $region ) {
				$known[ $region['id'] ] = $region;
				$changed                = true;
			}
		}

		if ( $changed ) {
			// Nicht unbegrenzt wachsen lassen.
			update_option( self::OPTION, array_slice( $known, -500, null, true ), false );
		}
	}

	/**
	 * @param mixed $id
	 */
	public static function is_valid_id( $id ) {
		return is_scalar( $id ) && preg_match( '/^[125789]\d{8}$/', (string) $id );
	}

	/**
	 * Gemeinden (8…) kommen aus dem DWD-Geodienst, alle anderen aus warnings.json.
	 */
	public static function is_municipality( $id ) {
		return '8' === substr( (string) $id, 0, 1 );
	}

	/**
	 * Suche über die Wetterwarner-API (Ergebnisse werden einen Tag zwischengespeichert).
	 *
	 * @param string $search Suchbegriff.
	 * @param int    $limit  Maximale Treffer.
	 * @return array[]|WP_Error
	 */
	public static function search( $search, $limit = 20 ) {
		$search = trim( (string) $search );
		$demo   = in_array( strtolower( $search ), array( 'demo', 'test', '100' ), true ) ? array( self::get( self::DEMO ) ) : array();

		if ( strlen( $search ) < 2 ) {
			return $demo;
		}

		$key     = 'wetterwarner_search_' . md5( strtolower( $search ) . '|' . $limit );
		$results = get_transient( $key );

		if ( ! is_array( $results ) ) {
			$response = self::api_request(
				add_query_arg(
					array(
						'search' => rawurlencode( $search ),
						'limit'  => (int) $limit,
					),
					'regions'
				)
			);

			if ( is_wp_error( $response ) || ! isset( $response['regions'] ) || ! is_array( $response['regions'] ) ) {
				// API nicht erreichbar: eine eingegebene Warncell-ID trotzdem zulassen.
				if ( self::is_valid_id( $search ) ) {
					return array_merge( $demo, array( self::get( $search ) ) );
				}
				if ( $demo ) {
					return $demo;
				}
				return is_wp_error( $response ) ? $response : new WP_Error( 'wetterwarner_search', __( 'The API response could not be read.', 'wetterwarner' ) );
			}

			$results = array_values( array_map( array( __CLASS__, 'clean' ), array_filter( $response['regions'], array( __CLASS__, 'is_region' ) ) ) );
			set_transient( $key, $results, self::SEARCH_CACHE );
		}

		return array_merge( $demo, $results );
	}

	/**
	 * Übersetzt eine alte wettwarn.de Feed-ID (z. B. "HAN") in eine Warncell-ID.
	 *
	 * @param string $feed_id Alte Feed-ID.
	 * @return string Warncell-ID, "demo" oder leer.
	 */
	public static function from_legacy_feed_id( $feed_id ) {
		$feed_id = strtoupper( trim( (string) $feed_id ) );
		if ( '100' === $feed_id ) {
			return self::DEMO;
		}
		$map = require WETTERWARNER_DIR . 'data/ccc-map.php';
		return isset( $map[ $feed_id ] ) ? (string) $map[ $feed_id ] : '';
	}

	/**
	 * Lesbare Bezeichnung des Regionstyps.
	 */
	public static function type_label( $type ) {
		$labels = array(
			'k' => __( 'District', 'wetterwarner' ),
			't' => __( 'District area', 'wetterwarner' ),
			'g' => __( 'Municipality', 'wetterwarner' ),
			's' => __( 'Inland lake', 'wetterwarner' ),
			'c' => __( 'Coast', 'wetterwarner' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : '';
	}

	public static function type_from_id( $id ) {
		$types = array(
			'1' => 'k',
			'2' => 's',
			'5' => 'c',
			'8' => 'g',
			'9' => 't',
		);
		$first = substr( (string) $id, 0, 1 );
		return isset( $types[ $first ] ) ? $types[ $first ] : '';
	}

	/**
	 * @param mixed $region
	 */
	public static function is_region( $region ) {
		return is_array( $region ) && isset( $region['id'], $region['name'] ) && self::is_valid_id( $region['id'] );
	}

	/**
	 * Übernimmt nur erwartete Felder aus API-Daten.
	 */
	public static function clean( array $region ) {
		return array(
			'id'    => (string) $region['id'],
			'name'  => sanitize_text_field( (string) $region['name'] ),
			'state' => isset( $region['state'] ) && preg_match( '/^[A-Z]{2}$/', (string) $region['state'] ) ? (string) $region['state'] : '',
			'type'  => self::type_from_id( $region['id'] ),
		);
	}

	/**
	 * @param string $path Pfad relativ zur API-Basis-URL.
	 * @return array|WP_Error Dekodierte JSON-Antwort.
	 */
	private static function api_request( $path ) {
		$base = Source::api_url();
		if ( '' === $base ) {
			return new WP_Error( 'wetterwarner_api_disabled', __( 'Weather alerts could not be loaded.', 'wetterwarner' ) );
		}

		$response = wp_remote_get(
			trailingslashit( $base ) . $path,
			array(
				'timeout'    => 8,
				'user-agent' => 'Wetterwarner/' . WETTERWARNER_VERSION . ' (WordPress; +https://wordpress.org/plugins/wetterwarner/)',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'wetterwarner_http', sprintf( __( 'Data source responded with HTTP status %d.', 'wetterwarner' ), wp_remote_retrieve_response_code( $response ) ) );
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $json ) ? $json : new WP_Error( 'wetterwarner_bad_json', __( 'The API response could not be read.', 'wetterwarner' ) );
	}
}
