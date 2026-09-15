<?php
/**
 * Warnregionen (DWD Warncell-IDs): Suche, Lookup und Migration alter Feed-IDs.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

defined( 'ABSPATH' ) || exit;

class Regions {

	/** Pseudo-Region mit Beispielmeldungen (ersetzt die alte Feed-ID "100"). */
	const DEMO = 'demo';

	/** @var array<int, array{0:int,1:string,2:string,3:string}>|null */
	private static $list = null;

	/** @var array<int, int>|null Warncell-ID => Index in $list */
	private static $index = null;

	/**
	 * Alle Regionen: array( id, name, bundesland, typ ).
	 */
	public static function all() {
		if ( null === self::$list ) {
			self::$list = require WETTERWARNER_DIR . 'data/regions.php';
		}
		return self::$list;
	}

	/**
	 * Region als assoziatives Array oder null.
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
		if ( null === self::$index ) {
			self::$index = array();
			foreach ( self::all() as $i => $row ) {
				self::$index[ $row[0] ] = $i;
			}
		}
		$id = (int) $id;
		return isset( self::$index[ $id ] ) ? self::format( self::all()[ self::$index[ $id ] ] ) : null;
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
	 * Volltextsuche über Name und Warncell-ID.
	 *
	 * @param string $search Suchbegriff.
	 * @param int    $limit  Maximale Treffer.
	 */
	public static function search( $search, $limit = 20 ) {
		$needle = self::normalize( $search );
		$demo   = array();

		if ( '' === $needle ) {
			return array();
		}
		if ( in_array( $needle, array( 'demo', 'test', '100' ), true ) ) {
			$demo[] = self::get( self::DEMO );
		}

		$buckets = array( array(), array(), array() );
		$digits  = ctype_digit( $needle );

		foreach ( self::all() as $row ) {
			if ( $digits ) {
				if ( 0 === strpos( (string) $row[0], $needle ) ) {
					$buckets[0][] = $row;
				}
				continue;
			}

			$name = self::normalize( $row[1] );
			$pos  = strpos( $name, $needle );
			if ( false === $pos ) {
				continue;
			}
			// Ohne Präfix wie "Stadt", "Kreis", "Gemeinde" vergleichen.
			$bare = preg_replace( '/^(stadt|kreis|landkreis|gemeinde|markt|region|hansestadt|kreis und stadt)\s+/', '', $name );
			if ( 0 === strpos( $bare, $needle ) || 0 === $pos ) {
				$buckets[ $bare === $needle ? 0 : 1 ][] = $row;
			} else {
				$buckets[2][] = $row;
			}
		}

		$results = array();
		foreach ( $buckets as $bucket ) {
			// Innerhalb eines Buckets: Landkreise vor Kreisteilen vor Gemeinden.
			usort(
				$bucket,
				static function ( $a, $b ) {
					$order = array(
						'k' => 0,
						't' => 1,
						'g' => 2,
						's' => 3,
						'c' => 4,
					);
					return array( $order[ $a[3] ], $a[1] ) <=> array( $order[ $b[3] ], $b[1] );
				}
			);
			foreach ( $bucket as $row ) {
				$results[] = self::format( $row );
				if ( count( $results ) >= $limit ) {
					break 2;
				}
			}
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

	private static function format( array $row ) {
		return array(
			'id'    => (string) $row[0],
			'name'  => $row[1],
			'state' => $row[2],
			'type'  => $row[3],
		);
	}

	private static function normalize( $text ) {
		$text = mb_strtolower( trim( (string) $text ), 'UTF-8' );
		$text = strtr(
			$text,
			array(
				'ä' => 'a',
				'ö' => 'o',
				'ü' => 'u',
				'ß' => 'ss',
				'é' => 'e',
			)
		);
		return preg_replace( '/\s+/', ' ', $text );
	}
}
