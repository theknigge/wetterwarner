<?php
/**
 * Aktuell trotz Seiten-Cache: Die ausgegebenen Warnungen tragen eine signierte
 * Beschreibung ihrer selbst. Ein kleines Skript holt damit den aktuellen Stand
 * von der eigenen Website – Besucher verbinden sich nie mit externen Servern.
 *
 * GET /wp-json/wetterwarner/v1/render?w=<Daten>&s=<Signatur>
 *
 * Die Signatur verhindert, dass beliebige Regionen abgefragt und damit
 * API-Abrufe ausgelöst werden können.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class Live {

	const HANDLE = 'wetterwarner-live';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_script' ) );
	}

	/** Standardmäßig an, abschaltbar unter "Einstellungen > Wetterwarner". */
	public static function enabled() {
		$options = (array) get_option( Admin::OPTION, array() );
		return ! isset( $options['live_refresh'] ) || $options['live_refresh'];
	}

	public static function register_script() {
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}
		wp_register_script( self::HANDLE, WETTERWARNER_URL . 'assets/js/live.js', array(), WETTERWARNER_VERSION, true );
		wp_add_inline_script(
			self::HANDLE,
			'window.wetterwarnerLive=' . wp_json_encode(
				array(
					'url'      => rest_url( Rest::NAMESPACE_V1 . '/render' ),
					'maxStale' => Source::MAX_STALE,
					'dwdUrl'   => Renderer::DWD_URL,
					'error'    => __( 'Weather alerts are currently unavailable.', 'wetterwarner' ),
					'link'     => __( 'View alerts at dwd.de', 'wetterwarner' ),
				),
				JSON_HEX_TAG | JSON_HEX_AMP
			) . ';',
			'before'
		);
	}

	public static function enqueue_script() {
		self::register_script();
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * HTML-Attribute, mit denen das Skript den Stand nachladen kann.
	 *
	 * @param array $data Beschreibung der Ausgabe.
	 * @param int   $time Stand der Daten (Unix-Sekunden), 0 = keine Daten.
	 */
	public static function attributes( array $data, $time ) {
		if ( ! self::enabled() ) {
			return '';
		}
		self::enqueue_script();
		$payload = base64_encode( wp_json_encode( $data ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		return sprintf(
			' data-ww-live="%1$s" data-ww-sig="%2$s" data-ww-time="%3$d"',
			esc_attr( $payload ),
			esc_attr( self::sign( $payload ) ),
			(int) $time
		);
	}

	private static function sign( $payload ) {
		return hash_hmac( 'sha256', 'wetterwarner|' . $payload, wp_salt( 'nonce' ) );
	}

	public static function register_route() {
		register_rest_route(
			Rest::NAMESPACE_V1,
			'/render',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'render' ),
				// Öffentlich: Die Signatur beschränkt die Route auf Ausgaben dieser Website.
				'permission_callback' => '__return_true',
				'args'                => array(
					'w' => array(
						'type'     => 'string',
						'required' => true,
					),
					's' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	public static function render( WP_REST_Request $request ) {
		$payload = (string) $request['w'];
		if ( ! self::enabled() || ! hash_equals( self::sign( $payload ), (string) $request['s'] ) ) {
			return new WP_Error( 'wetterwarner_forbidden', 'Invalid signature.', array( 'status' => 403 ) );
		}
		$data = json_decode( (string) base64_decode( $payload, true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'wetterwarner_bad_request', 'Invalid data.', array( 'status' => 400 ) );
		}

		if ( ! empty( $data['banner'] ) ) {
			$result = array(
				'html'   => Banner::html(),
				'before' => '',
				'after'  => '',
			);
		} else {
			$context = array_intersect_key( (array) ( $data['c'] ?? array() ), array_flip( array( 'wrapper', 'before_title', 'after_title' ) ) );
			$result  = array(
				'html'   => Renderer::render( (array) ( $data['a'] ?? array() ), array_merge( $context, array( 'live' => $data ) ) ),
				'before' => (string) ( $data['b'] ?? '' ),
				'after'  => (string) ( $data['e'] ?? '' ),
			);
		}

		$response = new WP_REST_Response( $result );
		$response->header( 'Cache-Control', 'public, max-age=60' );
		return $response;
	}
}
