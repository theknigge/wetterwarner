<?php
/**
 * REST-Endpunkte für die Regionsauswahl in Block- und Widget-Editor.
 *
 * GET /wp-json/wetterwarner/v1/regions?search=hannover
 * GET /wp-json/wetterwarner/v1/regions/103241000
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class Rest {

	const NAMESPACE_V1 = 'wetterwarner/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/regions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 50,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/regions/(?P<id>demo|\d{9})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'single' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			)
		);
	}

	public static function can_edit() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_theme_options' );
	}

	public static function search( WP_REST_Request $request ) {
		return rest_ensure_response( array_map( array( __CLASS__, 'prepare' ), Regions::search( $request['search'], (int) $request['per_page'] ) ) );
	}

	public static function single( WP_REST_Request $request ) {
		$region = Regions::get( $request['id'] );
		if ( ! $region ) {
			return new WP_Error( 'wetterwarner_not_found', __( 'Warning region not found.', 'wetterwarner' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::prepare( $region ) );
	}

	/**
	 * Ergänzt eine lesbare Beschriftung, z. B. "Region Hannover (Landkreis, NI)".
	 */
	public static function prepare( array $region ) {
		$meta            = array_filter( array( Regions::type_label( $region['type'] ), $region['state'] ) );
		$region['label'] = $region['name'] . ( $meta && Regions::DEMO !== $region['id'] ? ' (' . implode( ', ', $meta ) . ')' : '' );
		return $region;
	}
}
