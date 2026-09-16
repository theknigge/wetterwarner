<?php
/**
 * Datenquelle: amtliche Warnungen des Deutschen Wetterdienstes.
 *
 * Primär über die Wetterwarner-API (api.wetterwarner.de), die die DWD-Daten zentral
 * zwischenspeichert. Fällt die API aus, lädt das Plugin direkt beim DWD:
 * - Landkreise, Kreisteile, Küsten, Binnenseen: warnings.json
 * - Gemeinden: DWD-Geodienst (WFS "Warnungen_Gemeinden")
 *
 * Lastbegrenzung: Alle genutzten Regionen werden gemeinsam in einer Anfrage
 * geladen, regulär höchstens alle 5 Minuten (WP-Cron). Seitenaufrufe lesen
 * nur den lokalen Speicher. Ausnahme: eine neu gewählte Region wird einmalig
 * sofort geladen (höchstens einmal pro Minute).
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class Source {

	const API_URL      = 'https://api.wetterwarner.de/v3/';
	const DISTRICT_URL = 'https://www.dwd.de/DWD/warnungen/warnapp/json/warnings.json';
	const WFS_URL      = 'https://maps.dwd.de/geoserver/dwd/ows';

	/** Mindestabstand zwischen zwei regulären Abrufen (Sekunden). */
	const REFRESH_INTERVAL = 300;

	/** Ab diesem Alter wird auch ohne Cron nachgeladen (Sekunden). */
	const MAX_AGE = 1800;

	const STORE_OPTION  = 'wetterwarner_store';
	const STATUS_OPTION = 'wetterwarner_status';
	const LOCK          = 'wetterwarner_refresh_lock';

	/**
	 * Aktuelle Warnungen einer Region aus dem lokalen Speicher.
	 *
	 * @param string $region_id Warncell-ID oder "demo".
	 * @return array[]|WP_Error
	 */
	public static function get_warnings( $region_id ) {
		$region_id = (string) $region_id;

		if ( Regions::DEMO === $region_id ) {
			$warnings = self::demo_warnings();
		} elseif ( ! Regions::is_valid_id( $region_id ) ) {
			return new WP_Error( 'wetterwarner_invalid_region', __( 'Invalid warning region.', 'wetterwarner' ) );
		} else {
			$store = self::store();

			if ( ! isset( $store['cells'][ $region_id ] ) ) {
				self::refresh( array( $region_id ), true );
				$store = self::store();
			} elseif ( time() - $store['fetched'][ $region_id ] > self::MAX_AGE ) {
				// WP-Cron läuft offenbar nicht – im Rahmen des 5-Minuten-Limits nachladen.
				self::refresh( array_merge( array_keys( Plugin::usage()['regions'] ), array( $region_id ) ) );
				$store = self::store();
			}

			if ( ! isset( $store['cells'][ $region_id ] ) ) {
				return new WP_Error( 'wetterwarner_unavailable', self::last_error() );
			}
			$warnings = $store['cells'][ $region_id ];
		}

		/**
		 * Filtert die normalisierten Warnungen einer Region.
		 *
		 * @param array[] $warnings  Warnungen.
		 * @param string  $region_id Warncell-ID.
		 */
		$warnings = apply_filters( 'wetterwarner_warnings', $warnings, $region_id );

		return self::sort_and_filter( $warnings );
	}

	/**
	 * Lädt die Warnungen mehrerer Regionen in einer Anfrage.
	 *
	 * @param string[] $region_ids Warncell-IDs.
	 * @param bool     $new_region Einmaliger Sofortabruf für eine neue Region.
	 * @return bool|WP_Error true bei Erfolg, false wenn übersprungen.
	 */
	public static function refresh( array $region_ids, $new_region = false ) {
		$ids = array_values( array_unique( array_filter( array_map( 'strval', $region_ids ), array( Regions::class, 'is_valid_id' ) ) ) );
		if ( ! $ids ) {
			return false;
		}

		$store = self::store();
		if ( $new_region ) {
			$attempt = 'wetterwarner_try_' . md5( implode( ',', $ids ) );
			if ( get_transient( $attempt ) ) {
				return false;
			}
			set_transient( $attempt, 1, MINUTE_IN_SECONDS );
		} elseif ( time() - $store['last_request'] < self::REFRESH_INTERVAL - 30 ) {
			// 30 Sekunden Toleranz, damit ein leicht verspäteter Cron-Lauf nicht übersprungen wird.
			return false;
		}

		if ( get_transient( self::LOCK ) ) {
			return false;
		}
		set_transient( self::LOCK, 1, MINUTE_IN_SECONDS );

		if ( ! $new_region ) {
			// Vor dem Abruf merken: auch ein Fehlschlag zählt als Abruf.
			$store['last_request'] = time();
			self::save_store( $store );
		}

		$result = self::fetch_from_api( $ids );
		if ( is_wp_error( $result ) ) {
			$direct = self::fetch_direct( $ids );
			$result = is_wp_error( $direct ) ? $result : $direct;
		}

		delete_transient( self::LOCK );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$store = self::store();
		$now   = time();
		foreach ( $result as $id => $warnings ) {
			$store['cells'][ $id ]   = $warnings;
			$store['fetched'][ $id ] = $now;
		}
		// Nicht mehr genutzte Regionen entfernen.
		foreach ( $store['fetched'] as $id => $time ) {
			if ( $now - $time > 2 * DAY_IN_SECONDS ) {
				unset( $store['cells'][ $id ], $store['fetched'][ $id ] );
			}
		}
		self::save_store( $store );

		return true;
	}

	/**
	 * Basis-URL der Wetterwarner-API. Leer = nur direkt beim DWD laden.
	 */
	public static function api_url() {
		$url = defined( 'WETTERWARNER_API_URL' ) ? WETTERWARNER_API_URL : self::API_URL;

		/**
		 * Basis-URL der Wetterwarner-API, z. B. für eine eigene Instanz.
		 *
		 * @param string $url URL mit abschließendem Slash oder leer.
		 */
		return (string) apply_filters( 'wetterwarner_api_url', $url );
	}

	/**
	 * Löscht alle zwischengespeicherten Warnungen.
	 */
	public static function clear_cache() {
		delete_option( self::STORE_OPTION );
		delete_transient( self::LOCK );
	}

	/**
	 * Status der letzten Abrufe (für Einstellungen und Website-Zustand).
	 *
	 * @return array<string, array>
	 */
	public static function status() {
		return (array) get_option( self::STATUS_OPTION, array() );
	}

	/**
	 * @return array{cells: array<string, array[]>, fetched: array<string, int>, last_request: int}
	 */
	public static function store() {
		$store = get_option( self::STORE_OPTION, array() );
		return array(
			'cells'        => isset( $store['cells'] ) ? (array) $store['cells'] : array(),
			'fetched'      => isset( $store['fetched'] ) ? (array) $store['fetched'] : array(),
			'last_request' => isset( $store['last_request'] ) ? (int) $store['last_request'] : 0,
		);
	}

	private static function save_store( array $store ) {
		update_option( self::STORE_OPTION, $store, false );
	}

	private static function last_error() {
		foreach ( array( 'api', 'districts', 'municipalities' ) as $source ) {
			$status = self::status();
			if ( ! empty( $status[ $source ]['error'] ) ) {
				return $status[ $source ]['error'];
			}
		}
		return __( 'Weather alerts could not be loaded.', 'wetterwarner' );
	}

	/* ------------------------------------------------------------------ */
	/* Wetterwarner-API                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * @param string[] $ids Warncell-IDs.
	 * @return array<string, array[]>|WP_Error
	 */
	private static function fetch_from_api( array $ids ) {
		$base = self::api_url();
		if ( '' === $base ) {
			return new WP_Error( 'wetterwarner_api_disabled', 'API disabled' );
		}

		$result = array();
		foreach ( array_chunk( $ids, 100 ) as $chunk ) {
			$body = self::request( add_query_arg( 'cells', implode( ',', $chunk ), trailingslashit( $base ) . 'warnings' ), 'api' );
			if ( is_wp_error( $body ) ) {
				return $body;
			}

			$json = json_decode( $body, true );
			if ( ! is_array( $json ) || ! isset( $json['cells'] ) || ! is_array( $json['cells'] ) ) {
				$error = new WP_Error( 'wetterwarner_bad_json', __( 'The API response could not be read.', 'wetterwarner' ) );
				self::set_status( 'api', $error );
				return $error;
			}

			foreach ( $chunk as $id ) {
				$list          = isset( $json['cells'][ $id ] ) && is_array( $json['cells'][ $id ] ) ? $json['cells'][ $id ] : array();
				$result[ $id ] = array_map( array( __CLASS__, 'sanitize_warning' ), array_filter( $list, 'is_array' ) );
			}

			// Name, Bundesland und Typ der Regionen kommen gleich mit.
			if ( isset( $json['regions'] ) && is_array( $json['regions'] ) ) {
				Regions::remember( $json['regions'] );
			}
		}

		return $result;
	}

	/**
	 * Stellt sicher, dass Daten der API dem erwarteten Format entsprechen.
	 */
	private static function sanitize_warning( array $w ) {
		$types = array( 'thunderstorm', 'wind', 'rain', 'snow', 'fog', 'frost', 'ice', 'thaw', 'heat', 'uv', 'other' );
		$text  = static function ( $key ) use ( $w ) {
			return isset( $w[ $key ] ) && is_scalar( $w[ $key ] ) ? (string) $w[ $key ] : '';
		};
		$int   = static function ( $key ) use ( $w ) {
			return isset( $w[ $key ] ) && is_numeric( $w[ $key ] ) ? (int) $w[ $key ] : 0;
		};

		return array(
			'event'          => $text( 'event' ),
			'headline'       => $text( 'headline' ),
			'description'    => $text( 'description' ),
			'instruction'    => $text( 'instruction' ),
			'stage'          => max( 1, min( 4, $int( 'stage' ) ) ),
			'type'           => in_array( $text( 'type' ), $types, true ) ? $text( 'type' ) : 'other',
			'start'          => $int( 'start' ),
			'end'            => $int( 'end' ),
			'prior'          => ! empty( $w['prior'] ),
			'altitude_start' => $int( 'altitude_start' ),
			'altitude_end'   => $int( 'altitude_end' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Rückfall: direkt beim DWD                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * @param string[] $ids Warncell-IDs.
	 * @return array<string, array[]>|WP_Error
	 */
	private static function fetch_direct( array $ids ) {
		$districts = array();
		$fallback  = array();
		$result    = array();

		$municipalities = array_filter( $ids, array( Regions::class, 'is_municipality' ) );
		foreach ( array_chunk( $municipalities, 50 ) as $chunk ) {
			$fetched = self::fetch_municipalities( $chunk );
			if ( is_wp_error( $fetched ) ) {
				// Geodienst nicht erreichbar: Warnungen des Landkreises verwenden.
				foreach ( $chunk as $id ) {
					$fallback[ $id ] = '1' . substr( $id, 1, 5 ) . '000';
				}
			} else {
				$result += $fetched;
			}
		}

		foreach ( $ids as $id ) {
			if ( ! Regions::is_municipality( $id ) ) {
				$districts[] = $id;
			}
		}

		if ( $districts || $fallback ) {
			$feed = self::load_district_feed();
			if ( is_wp_error( $feed ) ) {
				return $result ? $result : $feed;
			}
			foreach ( $districts as $id ) {
				$result[ $id ] = self::district_warnings( $feed, $id );
			}
			foreach ( $fallback as $id => $district ) {
				$result[ $id ] = self::district_warnings( $feed, $district );
			}
		}

		return $result;
	}

	/**
	 * Warnungen einer Region aus warnings.json, inklusive verwandter Zellen:
	 * Landkreise (1…) sind teils in Kreisteile (9…) aufgeteilt und umgekehrt.
	 */
	private static function district_warnings( array $feed, $region_id ) {
		$kreis  = substr( $region_id, 1, 5 );
		$type   = substr( $region_id, 0, 1 );
		$result = array();
		$seen   = array();

		foreach ( $feed as $cell => $warnings ) {
			$cell  = (string) $cell;
			$match = $cell === $region_id
				|| ( '1' === $type && 0 === strpos( $cell, '9' . $kreis ) )
				|| ( '9' === $type && '1' . $kreis . '000' === $cell );

			if ( ! $match ) {
				continue;
			}
			foreach ( $warnings as $warning ) {
				$key = md5( $warning['event'] . '|' . $warning['start'] . '|' . $warning['end'] . '|' . $warning['stage'] );
				if ( ! isset( $seen[ $key ] ) ) {
					$seen[ $key ] = true;
					$result[]     = $warning;
				}
			}
		}

		return $result;
	}

	/**
	 * @return array<string, array[]>|WP_Error Normalisierte Warnungen je Zelle.
	 */
	private static function load_district_feed() {
		/**
		 * URL der DWD-Warnungen auf Kreisebene (JSON/JSONP).
		 *
		 * @param string $url URL.
		 */
		$body = self::request( apply_filters( 'wetterwarner_district_feed_url', self::DISTRICT_URL ), 'districts', 30 );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$json = json_decode( preg_replace( '/^\s*warnWetter\.loadWarnings\(|\);?\s*$/', '', $body ), true );
		if ( ! is_array( $json ) || ! isset( $json['warnings'] ) ) {
			$error = new WP_Error( 'wetterwarner_bad_json', __( 'The DWD response could not be read.', 'wetterwarner' ) );
			self::set_status( 'districts', $error );
			return $error;
		}

		$cells = array();
		foreach ( array( 'warnings' => false, 'vorabInformation' => true ) as $group => $prior ) {
			if ( empty( $json[ $group ] ) || ! is_array( $json[ $group ] ) ) {
				continue;
			}
			foreach ( $json[ $group ] as $cell => $list ) {
				foreach ( (array) $list as $warning ) {
					$cells[ $cell ][] = self::normalize_district( $warning, $prior );
				}
			}
		}
		return $cells;
	}

	private static function normalize_district( array $w, $prior ) {
		$level = isset( $w['level'] ) ? (int) $w['level'] : 2;

		if ( $level >= 20 ) {
			$stage = 1; // UV.
		} elseif ( $level >= 10 ) {
			$stage = 11 === $level ? 3 : 2; // Hitze.
		} else {
			$stage = max( 1, min( 4, $level - 1 ) );
		}

		return array(
			'event'          => isset( $w['event'] ) ? (string) $w['event'] : '',
			'headline'       => isset( $w['headline'] ) ? (string) $w['headline'] : '',
			'description'    => isset( $w['description'] ) ? (string) $w['description'] : '',
			'instruction'    => isset( $w['instruction'] ) ? (string) $w['instruction'] : '',
			'stage'          => $stage,
			'type'           => self::event_type( isset( $w['event'] ) ? $w['event'] : '', isset( $w['type'] ) ? $w['type'] : null ),
			'start'          => isset( $w['start'] ) ? (int) ( $w['start'] / 1000 ) : 0,
			'end'            => ! empty( $w['end'] ) ? (int) ( $w['end'] / 1000 ) : 0,
			'prior'          => $prior || 1 === $level,
			'altitude_start' => ! empty( $w['altitudeStart'] ) ? (int) $w['altitudeStart'] : 0,
			'altitude_end'   => ! empty( $w['altitudeEnd'] ) ? (int) $w['altitudeEnd'] : 0,
		);
	}

	/**
	 * @param string[] $ids Warncell-IDs von Gemeinden.
	 * @return array<string, array[]>|WP_Error
	 */
	private static function fetch_municipalities( array $ids ) {
		$ids = array_values( array_map( 'strval', $ids ) );

		$url = add_query_arg(
			array(
				'service'      => 'WFS',
				'version'      => '2.0.0',
				'request'      => 'GetFeature',
				'typeName'     => 'dwd:Warnungen_Gemeinden',
				'outputFormat' => 'application/json',
				'propertyName' => 'WARNCELLID,STATUS,MSGTYPE,EVENT,HEADLINE,DESCRIPTION,INSTRUCTION,SEVERITY,URGENCY,ONSET,EXPIRES,ALTITUDE,CEILING',
				'CQL_FILTER'   => rawurlencode( 'WARNCELLID IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' ),
			),
			/**
			 * URL des DWD-Geodienstes (WFS).
			 *
			 * @param string $url URL.
			 */
			apply_filters( 'wetterwarner_wfs_url', self::WFS_URL )
		);

		// Der Geodienst antwortet mitunter langsamer als warnings.json.
		$body = self::request( $url, 'municipalities', 20 );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$json = json_decode( $body, true );
		if ( ! is_array( $json ) || ! isset( $json['features'] ) ) {
			$error = new WP_Error( 'wetterwarner_bad_json', __( 'The DWD response could not be read.', 'wetterwarner' ) );
			self::set_status( 'municipalities', $error );
			return $error;
		}

		$result = array_fill_keys( $ids, array() );
		foreach ( $json['features'] as $feature ) {
			$p    = isset( $feature['properties'] ) ? $feature['properties'] : array();
			$cell = isset( $p['WARNCELLID'] ) ? (string) $p['WARNCELLID'] : '';
			if ( ! isset( $result[ $cell ] ) || ( isset( $p['STATUS'] ) && 'Actual' !== $p['STATUS'] ) || ( isset( $p['MSGTYPE'] ) && 'Cancel' === $p['MSGTYPE'] ) ) {
				continue;
			}
			$result[ $cell ][] = self::normalize_wfs( $p );
		}

		return $result;
	}

	private static function normalize_wfs( array $p ) {
		$stages = array(
			'Minor'    => 1,
			'Moderate' => 2,
			'Severe'   => 3,
			'Extreme'  => 4,
		);
		$feet   = static function ( $value ) {
			$meters = (int) round( (float) $value * 0.3048 );
			// Der DWD kodiert "ohne Obergrenze" als 9842,5 ft (3000 m).
			return $meters >= 3000 ? 0 : $meters;
		};

		return array(
			'event'          => isset( $p['EVENT'] ) ? (string) $p['EVENT'] : '',
			'headline'       => isset( $p['HEADLINE'] ) ? (string) $p['HEADLINE'] : '',
			'description'    => isset( $p['DESCRIPTION'] ) ? (string) $p['DESCRIPTION'] : '',
			'instruction'    => isset( $p['INSTRUCTION'] ) ? (string) $p['INSTRUCTION'] : '',
			'stage'          => isset( $p['SEVERITY'], $stages[ $p['SEVERITY'] ] ) ? $stages[ $p['SEVERITY'] ] : 1,
			'type'           => self::event_type( isset( $p['EVENT'] ) ? $p['EVENT'] : '' ),
			'start'          => ! empty( $p['ONSET'] ) ? (int) strtotime( $p['ONSET'] ) : 0,
			'end'            => ! empty( $p['EXPIRES'] ) ? (int) strtotime( $p['EXPIRES'] ) : 0,
			'prior'          => isset( $p['URGENCY'] ) && 'Future' === $p['URGENCY'],
			'altitude_start' => ! empty( $p['ALTITUDE'] ) ? $feet( $p['ALTITUDE'] ) : 0,
			'altitude_end'   => ! empty( $p['CEILING'] ) ? $feet( $p['CEILING'] ) : 0,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Hilfsfunktionen                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Ordnet ein DWD-Ereignis einer Icon-Kategorie zu.
	 *
	 * @param string   $event Ereignis, z. B. "STARKES GEWITTER".
	 * @param int|null $type  DWD-Typ aus warnings.json (Rückfall).
	 */
	public static function event_type( $event, $type = null ) {
		$event    = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( (string) $event, 'UTF-8' ) : strtoupper( (string) $event );
		$keywords = array(
			'thunderstorm' => array( 'GEWITTER' ),
			'heat'         => array( 'HITZE', 'WÄRMEBELASTUNG' ),
			'uv'           => array( 'UV' ),
			'fog'          => array( 'NEBEL' ),
			'ice'          => array( 'GLATTEIS', 'GLÄTTE', 'EISREGEN', 'EISBILDUNG' ),
			'frost'        => array( 'FROST' ),
			'thaw'         => array( 'TAUWETTER' ),
			'snow'         => array( 'SCHNEE' ),
			'rain'         => array( 'REGEN' ),
			'wind'         => array( 'BÖEN', 'STURM', 'ORKAN', 'WIND' ),
		);
		foreach ( $keywords as $key => $words ) {
			foreach ( $words as $word ) {
				if ( false !== strpos( $event, $word ) ) {
					return $key;
				}
			}
		}

		$types = array( 'thunderstorm', 'wind', 'rain', 'snow', 'fog', 'frost', 'ice', 'thaw', 'heat', 'uv' );
		return null !== $type && isset( $types[ (int) $type ] ) ? $types[ (int) $type ] : 'other';
	}

	/**
	 * Entfernt abgelaufene Warnungen und sortiert: Warnungen vor
	 * Vorabinformationen, höhere Stufe zuerst, dann nach Beginn.
	 */
	private static function sort_and_filter( array $warnings ) {
		$now      = time();
		$warnings = array_values(
			array_filter(
				$warnings,
				static function ( $w ) use ( $now ) {
					return empty( $w['end'] ) || $w['end'] > $now;
				}
			)
		);
		usort(
			$warnings,
			static function ( $a, $b ) {
				return array( (int) $a['prior'], -$a['stage'], $a['start'] ) <=> array( (int) $b['prior'], -$b['stage'], $b['start'] );
			}
		);
		return $warnings;
	}

	/**
	 * @param string $url     URL.
	 * @param string $source  Statusschlüssel: api, districts, municipalities.
	 * @param int    $timeout Sekunden.
	 * @return string|WP_Error Antworttext.
	 */
	private static function request( $url, $source, $timeout = 10 ) {
		$response = wp_remote_get(
			$url,
			array(
				// Freiwillige Nutzungsdaten nur an die eigene API und nur nach Zustimmung.
				'headers'    => 'api' === $source ? Telemetry::headers() : array(),
				'timeout'    => $timeout,
				'user-agent' => 'Wetterwarner/' . WETTERWARNER_VERSION . ' (WordPress; +https://wordpress.org/plugins/wetterwarner/)',
			)
		);

		if ( is_wp_error( $response ) ) {
			$error = new WP_Error(
				'wetterwarner_http',
				/* translators: %s: error message */
				sprintf( __( 'Data source could not be reached: %s', 'wetterwarner' ), $response->get_error_message() )
			);
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$error = new WP_Error(
				'wetterwarner_http',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Data source responded with HTTP status %d.', 'wetterwarner' ), wp_remote_retrieve_response_code( $response ) )
			);
		} else {
			self::set_status( $source, true );
			return wp_remote_retrieve_body( $response );
		}

		self::set_status( $source, $error );
		return $error;
	}

	/**
	 * @param string        $source Statusschlüssel.
	 * @param true|WP_Error $result Ergebnis.
	 */
	private static function set_status( $source, $result ) {
		$status = self::status();
		$entry  = isset( $status[ $source ] ) ? $status[ $source ] : array();

		if ( is_wp_error( $result ) ) {
			$entry['error']      = $result->get_error_message();
			$entry['error_time'] = time();
		} else {
			$entry['ok'] = time();
			unset( $entry['error'], $entry['error_time'] );
		}

		$status[ $source ] = $entry;
		update_option( self::STATUS_OPTION, $status, false );
	}

	/**
	 * Beispielmeldungen für die Demo-Region.
	 */
	private static function demo_warnings() {
		$hour = (int) ( floor( time() / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );

		$demo = array(
			array( 'STURMBÖEN', 'Amtliche WARNUNG vor STURMBÖEN', 2, 0, 6, false, 'Es treten Sturmböen mit Geschwindigkeiten um 70 km/h (20m/s, 38kn, Bft 8) aus westlicher Richtung auf.', 'ACHTUNG! Hinweis auf mögliche Gefahren: Es können zum Beispiel einzelne Äste herabstürzen. Achten Sie besonders auf herabfallende Gegenstände.' ),
			array( 'SCHWERES GEWITTER mit ORKANBÖEN', 'Amtliche UNWETTERWARNUNG vor SCHWEREM GEWITTER mit ORKANBÖEN', 3, 2, 4, false, 'Es treten Gewitter mit Orkanböen um 120 km/h (33m/s, 65kn, Bft 12) auf.', 'Gefahr durch: Blitzschlag (Lebensgefahr!); Bäume entwurzeln; Dächer werden beschädigt. Handlungsempfehlungen: Schließen Sie alle Fenster und Türen! Halten Sie sich nicht im Freien auf!' ),
			array( 'FROST', 'Amtliche WARNUNG vor FROST', 1, 12, 22, false, 'Es tritt leichter Frost zwischen -1 °C und -4 °C auf.', '' ),
			array( 'DAUERREGEN', 'VORABINFORMATION UNWETTER vor ERGIEBIGEM DAUERREGEN', 3, 24, 48, true, 'Voraussichtlich ergiebiger Dauerregen mit Niederschlagsmengen zwischen 60 l/m² und 80 l/m².', '' ),
		);

		return array_map(
			static function ( $d ) use ( $hour ) {
				return array(
					'event'          => $d[0],
					'headline'       => $d[1],
					'description'    => $d[6],
					'instruction'    => $d[7],
					'stage'          => $d[2],
					'type'           => Source::event_type( $d[0] ),
					'start'          => $hour + $d[3] * HOUR_IN_SECONDS,
					'end'            => $hour + $d[4] * HOUR_IN_SECONDS,
					'prior'          => $d[5],
					'altitude_start' => 'FROST' === $d[0] ? 400 : 0,
					'altitude_end'   => 0,
				);
			},
			$demo
		);
	}
}
