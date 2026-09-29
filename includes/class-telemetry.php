<?php
/**
 * "Entwicklung unterstützen": freiwillige Nutzungsdaten – und, unabhängig
 * davon, die Zuordnung einer gekauften Supporter-Lizenz zur Website.
 *
 * Plugin-, WordPress- und PHP-Version sowie die Speicherung der Website in der
 * Statistik-Übersicht der API erfolgen nur nach ausdrücklicher Zustimmung
 * (Opt-in) und sind jederzeit widerrufbar. Die Adresse der Website wird davon
 * unabhängig bei jedem Abruf mitgeschickt (X-Wetterwarner-License-Site) –
 * ohne sie könnte die API eine gekaufte Lizenz nie zuordnen. Beim Widerruf der
 * Nutzungsdaten wird die API gebeten, den Statistik-Eintrag zu löschen.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

defined( 'ABSPATH' ) || exit;

class Telemetry {

	/** "yes" = zugestimmt, "no" = abgelehnt, nicht vorhanden = noch nicht gefragt. */
	const OPTION = 'wetterwarner_telemetry';

	const PRIVACY_URL = 'https://wetterwarner.de/datenschutz';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_wetterwarner_telemetry', array( __CLASS__, 'handle_choice' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'option_changed' ), 10, 2 );
	}

	public static function enabled() {
		return 'yes' === get_option( self::OPTION );
	}

	public static function decided() {
		return in_array( get_option( self::OPTION ), array( 'yes', 'no' ), true );
	}

	/**
	 * Adresse der Website ohne Protokoll, z. B. "feuerwehr-musterstadt.de" oder "example.org/blog".
	 */
	public static function site() {
		$url  = home_url();
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		return strtolower( $host . ( '' !== $path ? '/' . $path : '' ) );
	}

	/**
	 * Zusätzliche Header für API-Anfragen.
	 *
	 * X-Wetterwarner-License-Site wird immer gesendet, unabhängig von der
	 * Einwilligung unten – sonst könnte die API eine gekaufte Supporter-Lizenz
	 * nie zuordnen. Enthält nur die ohnehin öffentliche Adresse der Website,
	 * keine Versions- oder sonstigen Daten. X-Wetterwarner-Site/-Env (für die
	 * freiwillige Statistik in Plugin::usage()) bleiben an die Zustimmung
	 * gebunden.
	 *
	 * @return array<string, string>
	 */
	public static function headers() {
		$headers = array( 'X-Wetterwarner-License-Site' => self::site() );
		if ( self::enabled() ) {
			$headers['X-Wetterwarner-Site'] = self::site();
			$headers['X-Wetterwarner-Env']  = 'wp=' . get_bloginfo( 'version' ) . ';php=' . PHP_VERSION;
		}
		return $headers;
	}

	/**
	 * Eigene Einstellungsgruppe: Der Abschnitt steht in einem eigenen Formular
	 * oben auf der Seite. In der Gruppe der Farben würde ein Speichern ohne
	 * Farbfelder sonst die Farben zurücksetzen.
	 */
	const GROUP = 'wetterwarner_telemetry';

	public static function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => '',
			)
		);

		add_settings_section( 'wetterwarner_telemetry', __( 'Usage data', 'wetterwarner' ), '__return_false', self::GROUP );
		add_settings_field(
			self::OPTION,
			__( 'Consent', 'wetterwarner' ),
			array( __CLASS__, 'field' ),
			self::GROUP,
			'wetterwarner_telemetry',
			array( 'label_for' => self::OPTION )
		);
	}

	/**
	 * Einwilligung zu den Nutzungsdaten – ganz unten auf der Einstellungsseite.
	 */
	public static function render() {
		echo '<hr><form action="options.php" method="post">';
		settings_fields( self::GROUP );
		do_settings_sections( self::GROUP );
		submit_button( null, 'secondary' );
		echo '</form>';
	}

	public static function sanitize( $value ) {
		return 'yes' === $value ? 'yes' : 'no';
	}

	public static function field() {
		printf(
			'<label><input type="checkbox" id="%1$s" name="%1$s" value="yes" %2$s> %3$s</label><p class="description">%4$s <a href="%5$s" target="_blank" rel="noopener">%6$s</a></p>',
			esc_attr( self::OPTION ),
			checked( self::enabled(), true, false ),
			esc_html__( 'Support development and share usage data', 'wetterwarner' ),
			esc_html__( 'Shares technical data with the Wetterwarner API – no visitor data. You can revoke your consent at any time; the stored information will then be deleted.', 'wetterwarner' ),
			esc_url( self::PRIVACY_URL ),
			esc_html__( 'Privacy policy', 'wetterwarner' )
		);
	}

	/**
	 * Einmalige Frage im Backend, bis eine Entscheidung getroffen wurde.
	 */
	public static function notice() {
		if ( self::decided() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'widgets', 'settings_page_' . Admin::PAGE ), true ) ) {
			return;
		}

		$link = static function ( $choice ) {
			return wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'wetterwarner_telemetry',
						'choice' => $choice,
					),
					admin_url( 'admin-post.php' )
				),
				'wetterwarner_telemetry'
			);
		};

		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong></p><p>%2$s <a href="%7$s" target="_blank" rel="noopener">%8$s</a></p><p><a class="button button-primary" href="%3$s">%4$s</a> <a class="button" href="%5$s">%6$s</a></p></div>',
			esc_html__( 'Support the development of Wetterwarner?', 'wetterwarner' ),
			esc_html__( 'Shares technical data with the Wetterwarner API – no visitor data. You can revoke your consent at any time.', 'wetterwarner' ),
			esc_url( $link( 'yes' ) ),
			esc_html__( 'Yes, share usage data', 'wetterwarner' ),
			esc_url( $link( 'no' ) ),
			esc_html__( 'No, thanks', 'wetterwarner' ),
			esc_url( self::PRIVACY_URL ),
			esc_html__( 'Privacy policy', 'wetterwarner' )
		);
	}

	public static function handle_choice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wetterwarner' ) );
		}
		check_admin_referer( 'wetterwarner_telemetry' );

		$choice = isset( $_GET['choice'] ) && 'yes' === $_GET['choice'] ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		update_option( self::OPTION, $choice, false );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Widerruf: gespeicherte Angaben bei der API löschen lassen.
	 */
	public static function option_changed( $old_value, $new_value ) {
		if ( 'yes' === $old_value && 'yes' !== $new_value ) {
			self::forget();
		}
	}

	public static function forget() {
		$base = Source::api_url();
		if ( '' === $base ) {
			return;
		}
		wp_remote_post(
			trailingslashit( $base ) . 'sites/forget',
			array(
				'timeout'    => 5,
				'headers'    => array( 'X-Wetterwarner-Site' => self::site() ),
				'user-agent' => 'Wetterwarner/' . WETTERWARNER_VERSION . ' (WordPress; +https://wordpress.org/plugins/wetterwarner/)',
			)
		);
	}
}
