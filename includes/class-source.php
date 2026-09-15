<?php
/**
 * Datenquelle: amtliche Warnungen des Deutschen Wetterdienstes.
 *
 * - Landkreise, Kreisteile, Küsten und Binnenseen: warnings.json (Objekteinbindung)
 * - Gemeinden: DWD-Geodienst (WFS "Warnungen_Gemeinden"), gefiltert nach Warncell-ID
 *
 * Alle Abrufe werden als Transient zwischengespeichert. Zusätzlich bleibt die
 * letzte erfolgreiche Antwort als Reserve erhalten, falls der DWD kurzzeitig
 * nicht erreichbar ist.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class Source {

	const DISTRICT_URL = 'https://www.dwd.de/DWD/warnungen/warnapp/json/warnings.json';
	const WFS_URL      = 'https://maps.dwd.de/geoserver/dwd/ows';

	const CACHE_TTL     = 300;
	const PREFIX        = 'wetterwarner_feed_';
	const BACKUP_OPTION = 'wetterwarner_backup';
	const STATUS_OPTION = 'wetterwarner_status';

	/**
	 * Aktuelle Warnungen einer Region, sortiert nach Warnstufe.
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
		} elseif ( Regions::is_municipality( $region_id ) ) {
			$warnings = self::municipality_warnings( $region_id );
		} else {
			$warnings = self::district_warnings( $region_id );
		}

		if ( is_wp_error( $warnings ) ) {
			return $warnings;
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
	 * Lädt die angegebenen Regionen vorab in den Cache (WP-Cron).
	 *
	 * @param string[] $region_ids Warncell-IDs.
	 */
	public static function prewarm( array $region_ids ) {
		$municipalities = array();
		$districts      = false;

		foreach ( $region_ids as $id ) {
			if ( ! Regions::is_valid_id( $id ) ) {
				continue;
			}
			if ( Regions::is_municipality( $id ) ) {
				$municipalities[] = (string) $id;
			} else {
				$districts = true;
			}
		}

		if ( $districts ) {
			self::load_district_feed( true );
		}
		// Bis zu 50 Gemeinden je Anfrage bündeln.
		foreach ( array_chunk( $municipalities, 50 ) as $chunk ) {
			self::fetch_municipalities( $chunk );
		}
	}

	/**
	 * Löscht alle zwischengespeicherten Warnungen.
	 */
	public static function clear_cache() {
		global $wpdb;

		$like = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $name ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
		}
		delete_option( self::BACKUP_OPTION );
	}

	/**
	 * Status der letzten Abrufe (für Einstellungen und Website-Zustand).
	 *
	 * @return array<string, array>
	 */
	public static function status() {
		return (array) get_option( self::STATUS_OPTION, array() );
	}

	/* ------------------------------------------------------------------ */
	/* Landkreise, Kreisteile, Küsten, Binnenseen: warnings.json           */
	/* ------------------------------------------------------------------ */

	private static function district_warnings( $region_id ) {
		$feed = self::load_district_feed();
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		// Landkreise sind teils in Kreisteile (9…) aufgeteilt und umgekehrt.
		$kreis    = substr( $region_id, 1, 5 );
		$related  = array( $region_id );
		$type     = substr( $region_id, 0, 1 );
		$result   = array();
		$seen     = array();

		foreach ( $feed['cells'] as $cell => $warnings ) {
			$cell = (string) $cell;
			$match = in_array( $cell, $related, true )
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
	 * @param bool $force Cache ignorieren.
	 * @return array{time:int, cells:array}|WP_Error
	 */
	private static function load_district_feed( $force = false ) {
		$key = self::PREFIX . 'districts';

		if ( ! $force ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		/**
		 * URL der DWD-Warnungen auf Kreisebene (JSON/JSONP).
		 *
		 * @param string $url URL.
		 */
		$url  = apply_filters( 'wetterwarner_district_feed_url', self::DISTRICT_URL );
		$body = self::request( $url, 'districts' );

		if ( ! is_wp_error( $body ) ) {
			$body = preg_replace( '/^\s*warnWetter\.loadWarnings\(|\);?\s*$/', '', $body );
			$json = json_decode( $body, true );

			if ( ! is_array( $json ) || ! isset( $json['warnings'] ) ) {
				$body = new WP_Error( 'wetterwarner_bad_json', __( 'The DWD response could not be read.', 'wetterwarner' ) );
				self::set_status( 'districts', $body );
			} else {
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
				$feed = array(
					'time'  => isset( $json['time'] ) ? (int) ( $json['time'] / 1000 ) : time(),
					'cells' => $cells,
				);
				set_transient( $key, $feed, self::CACHE_TTL );
				self::set_backup( 'districts', $feed );
				return $feed;
			}
		}

		$backup = self::get_backup( 'districts' );
		return null !== $backup ? $backup : $body;
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

	/* ------------------------------------------------------------------ */
	/* Gemeinden: DWD-Geodienst (WFS)                                      */
	/* ------------------------------------------------------------------ */

	private static function municipality_warnings( $region_id ) {
		$cached = get_transient( self::PREFIX . 'g' . $region_id );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = self::fetch_municipalities( array( $region_id ) );
		if ( ! is_wp_error( $result ) ) {
			return $result[ $region_id ];
		}

		$backup = self::get_backup( 'g' . $region_id );
		if ( null !== $backup ) {
			return $backup;
		}

		// Geodienst nicht erreichbar: Warnungen des zugehörigen Landkreises anzeigen.
		$district = '1' . substr( $region_id, 1, 5 ) . '000';
		if ( Regions::get( $district ) || Regions::get( '9' . substr( $region_id, 1, 5 ) . '999' ) ) {
			$fallback = self::district_warnings( Regions::get( $district ) ? $district : '9' . substr( $region_id, 1, 5 ) . '999' );
			if ( ! is_wp_error( $fallback ) ) {
				return $fallback;
			}
		}
		return $result;
	}

	/**
	 * @param string[] $ids Warncell-IDs von Gemeinden.
	 * @return array<string, array[]>|WP_Error
	 */
	private static function fetch_municipalities( array $ids ) {
		$ids = array_values( array_filter( array_map( 'strval', $ids ), array( Regions::class, 'is_municipality' ) ) );
		if ( ! $ids ) {
			return array();
		}

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

		foreach ( $result as $cell => $warnings ) {
			set_transient( self::PREFIX . 'g' . $cell, $warnings, self::CACHE_TTL );
			self::set_backup( 'g' . $cell, $warnings );
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
	 * @return string|WP_Error Antworttext.
	 */
	private static function request( $url, $source, $timeout = 10 ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'Wetterwarner/' . WETTERWARNER_VERSION . ' (WordPress; +https://wordpress.org/plugins/wetterwarner/)',
			)
		);

		if ( is_wp_error( $response ) ) {
			$error = new WP_Error(
				'wetterwarner_http',
				/* translators: %s: error message */
				sprintf( __( 'DWD could not be reached: %s', 'wetterwarner' ), $response->get_error_message() )
			);
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$error = new WP_Error(
				'wetterwarner_http',
				/* translators: %d: HTTP status code */
				sprintf( __( 'DWD responded with HTTP status %d.', 'wetterwarner' ), wp_remote_retrieve_response_code( $response ) )
			);
		} else {
			self::set_status( $source, true );
			return wp_remote_retrieve_body( $response );
		}

		self::set_status( $source, $error );
		return $error;
	}

	/**
	 * @param string        $source "districts" oder "municipalities".
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

	private static function set_backup( $key, $data ) {
		$backup         = (array) get_option( self::BACKUP_OPTION, array() );
		$backup[ $key ] = $data;
		update_option( self::BACKUP_OPTION, $backup, false );
	}

	private static function get_backup( $key ) {
		$backup = (array) get_option( self::BACKUP_OPTION, array() );
		return isset( $backup[ $key ] ) ? $backup[ $key ] : null;
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
