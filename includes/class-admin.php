<?php
/**
 * Einstellungsseite, Website-Zustand und Hinweise im Backend.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

defined( 'ABSPATH' ) || exit;

class Admin {

	const OPTION = 'wetterwarner_settings';
	const PAGE   = 'wetterwarner';

	const DEFAULT_COLORS = array(
		1 => 'rgba(255,255,170,0.5)',
		2 => 'rgba(255,218,188,0.5)',
		3 => 'rgba(255,204,204,0.5)',
		4 => 'rgba(198,155,198,0.5)',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'customize_controls_enqueue_scripts', array( __CLASS__, 'enqueue_widget_script' ) );
		add_action( 'admin_post_wetterwarner_clear_cache', array( __CLASS__, 'clear_cache' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WETTERWARNER_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'site_status_tests' ) );
		add_filter( 'debug_information', array( __CLASS__, 'debug_information' ) );
	}

	/**
	 * Hintergrundfarben je Warnstufe.
	 *
	 * @return array<int, string>
	 */
	public static function colors() {
		$options = (array) get_option( self::OPTION, array() );
		$colors  = array();
		foreach ( self::DEFAULT_COLORS as $stage => $default ) {
			$key              = 'ww_farbe_stufe' . $stage;
			$colors[ $stage ] = ! empty( $options[ $key ] ) && self::is_color( $options[ $key ] ) ? $options[ $key ] : $default;
		}
		return $colors;
	}

	public static function color_css() {
		$vars = '';
		foreach ( self::colors() as $stage => $color ) {
			$vars .= '--wetterwarner-stage-' . $stage . ':' . $color . ';';
		}
		return '.wetterwarner{' . $vars . '}';
	}

	public static function menu() {
		add_options_page( 'Wetterwarner', 'Wetterwarner', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	public static function register_settings() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'wetterwarner_colors', __( 'Colors', 'wetterwarner' ), array( __CLASS__, 'colors_intro' ), self::PAGE );

		foreach ( self::DEFAULT_COLORS as $stage => $default ) {
			add_settings_field(
				'ww_farbe_stufe' . $stage,
				/* translators: %d: warning level */
				sprintf( __( 'Background color level %d', 'wetterwarner' ), $stage ),
				array( __CLASS__, 'color_field' ),
				self::PAGE,
				'wetterwarner_colors',
				array( 'stage' => $stage )
			);
		}
	}

	public static function sanitize( $input ) {
		$output = array();
		foreach ( array_keys( self::DEFAULT_COLORS ) as $stage ) {
			$key = 'ww_farbe_stufe' . $stage;
			if ( ! empty( $input[ $key ] ) && self::is_color( trim( $input[ $key ] ) ) ) {
				$output[ $key ] = trim( $input[ $key ] );
			}
		}
		return $output;
	}

	private static function is_color( $value ) {
		return (bool) preg_match( '/^(#[0-9a-f]{3}|#[0-9a-f]{6}|#[0-9a-f]{8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\))$/i', (string) $value );
	}

	public static function colors_intro() {
		echo '<p>' . esc_html__( 'These colors apply to all blocks, shortcodes and widgets when "Color by warning level" is enabled.', 'wetterwarner' ) . '</p>';
	}

	public static function color_field( $args ) {
		$colors = self::colors();
		printf(
			'<input type="text" class="wetterwarner-color" name="%1$s[ww_farbe_stufe%2$d]" value="%3$s" data-alpha-enabled="true" data-default-color="%4$s">',
			esc_attr( self::OPTION ),
			(int) $args['stage'],
			esc_attr( $colors[ $args['stage'] ] ),
			esc_attr( self::DEFAULT_COLORS[ $args['stage'] ] )
		);
	}

	public static function enqueue( $hook ) {
		if ( 'settings_page_' . self::PAGE === $hook ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker-alpha', WETTERWARNER_URL . 'assets/js/wp-color-picker-alpha.js', array( 'wp-color-picker' ), '3.0.3', true );
			wp_add_inline_script( 'wp-color-picker-alpha', 'jQuery(function($){$(".wetterwarner-color").wpColorPicker();});' );
		}
		if ( 'widgets.php' === $hook ) {
			self::enqueue_widget_script();
		}
	}

	public static function enqueue_widget_script() {
		wp_enqueue_script( 'wetterwarner-widget-admin', WETTERWARNER_URL . 'assets/js/widget-admin.js', array( 'wp-api-fetch', 'wp-url' ), WETTERWARNER_VERSION, true );
		wp_enqueue_style( 'wetterwarner-widget-admin', WETTERWARNER_URL . 'assets/css/widget-admin.css', array(), WETTERWARNER_VERSION );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = Source::status();
		$usage  = Plugin::usage();
		?>
		<div class="wrap">
			<h1>Wetterwarner</h1>

			<?php if ( isset( $_GET['cache-cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cache cleared.', 'wetterwarner' ); ?></p></div>
			<?php endif; ?>

			<?php Telemetry::render_donation(); ?>

			<h2><?php esc_html_e( 'Usage', 'wetterwarner' ); ?></h2>
			<p><?php esc_html_e( 'Add the "Wetterwarner" block in the block editor or in a widget area. Alternatively use the shortcode:', 'wetterwarner' ); ?></p>
			<p><code>[wetterwarner region="103241000" map="60"]</code></p>
			<details>
				<summary><?php esc_html_e( 'All shortcode attributes', 'wetterwarner' ); ?></summary>
				<table class="widefat striped" style="max-width:48rem">
					<tbody>
						<tr><td><code>region</code></td><td><?php esc_html_e( 'Warncell ID of the region (required), or "demo"', 'wetterwarner' ); ?></td></tr>
						<tr><td><code>title</code>, <code>intro</code>, <code>no_warnings</code></td><td><?php esc_html_e( 'Texts, %region% is replaced', 'wetterwarner' ); ?></td></tr>
						<tr><td><code>max</code></td><td><?php esc_html_e( 'Maximum number of alerts (default 3, 0 = all)', 'wetterwarner' ); ?></td></tr>
						<tr><td><code>show_always</code>, <code>validity</code>, <code>details</code>, <code>icons</code>, <code>colors</code>, <code>link</code>, <code>hide_duplicates</code></td><td><?php esc_html_e( 'Options: 1 or 0', 'wetterwarner' ); ?></td></tr>
						<tr><td><code>map</code></td><td><?php esc_html_e( 'Map width in percent (0 = no map)', 'wetterwarner' ); ?></td></tr>
						<tr><td><code>map_region</code></td><td><code><?php echo esc_html( implode( ', ', array_keys( Map::choices() ) ) ); ?></code></td></tr>
					</tbody>
				</table>
			</details>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<h2><?php esc_html_e( 'Data status', 'wetterwarner' ); ?></h2>
			<table class="widefat striped" style="max-width:48rem">
				<tbody>
					<?php
					foreach ( array(
						'api'            => __( 'Wetterwarner API (api.it93.de)', 'wetterwarner' ),
						'districts'      => __( 'DWD direct: districts (fallback)', 'wetterwarner' ),
						'municipalities' => __( 'DWD direct: municipalities (fallback)', 'wetterwarner' ),
					) as $key => $label ) :
						$entry = isset( $status[ $key ] ) ? $status[ $key ] : array();
						?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td>
								<?php
								echo esc_html(
									! empty( $entry['ok'] )
										/* translators: %s: human readable time difference */
										? sprintf( __( 'Last update %s ago', 'wetterwarner' ), human_time_diff( $entry['ok'] ) )
										: __( 'Not loaded yet', 'wetterwarner' )
								);
								if ( ! empty( $entry['error'] ) ) {
									echo '<br><span style="color:#b32d2e">' . esc_html( $entry['error'] ) . '</span>';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<td><?php esc_html_e( 'Regions in use', 'wetterwarner' ); ?></td>
						<td>
							<?php
							$names = array();
							foreach ( array_keys( $usage['regions'] ) as $id ) {
								$region  = Regions::get( (string) $id );
								$names[] = $region ? $region['name'] . ' (' . $id . ')' : $id;
							}
							echo esc_html( $names ? implode( ', ', $names ) : '–' );
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="wetterwarner_clear_cache">
				<?php wp_nonce_field( 'wetterwarner_clear_cache' ); ?>
				<?php submit_button( __( 'Clear cache', 'wetterwarner' ), 'secondary' ); ?>
			</form>

			<?php Telemetry::render(); ?>
		</div>
		<?php
	}

	public static function clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wetterwarner' ) );
		}
		check_admin_referer( 'wetterwarner_clear_cache' );
		Source::clear_cache();
		Map::clear_cache();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE . '&cache-cleared=1' ) );
		exit;
	}

	public static function notices() {
		$unmatched = get_option( Plugin::MIGRATION_OPTION );
		if ( ! $unmatched || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		if ( isset( $_GET['wetterwarner-dismiss'] ) && check_admin_referer( 'wetterwarner_dismiss' ) ) {
			delete_option( Plugin::MIGRATION_OPTION );
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>Wetterwarner 3.0:</strong> %1$s</p><p><a class="button button-primary" href="%2$s">%3$s</a> <a class="button" href="%4$s">%5$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: list of old feed IDs */
					__( 'Weather alerts now come directly from the Deutscher Wetterdienst. The following old feed IDs could not be assigned to a warning region automatically: %s. Please select the region again in the widget settings.', 'wetterwarner' ),
					implode( ', ', (array) $unmatched )
				)
			),
			esc_url( admin_url( 'widgets.php' ) ),
			esc_html__( 'Open widgets', 'wetterwarner' ),
			esc_url( wp_nonce_url( add_query_arg( 'wetterwarner-dismiss', '1' ), 'wetterwarner_dismiss' ) ),
			esc_html__( 'Dismiss', 'wetterwarner' )
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'wetterwarner' ) . '</a>' );
		return $links;
	}

	public static function site_status_tests( $tests ) {
		$tests['direct']['wetterwarner'] = array(
			'label' => 'Wetterwarner',
			'test'  => array( __CLASS__, 'site_status_test' ),
		);
		return $tests;
	}

	public static function site_status_test() {
		$result = array(
			'label'       => __( 'Wetterwarner receives current weather alerts', 'wetterwarner' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'Wetterwarner',
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'The alerts of the Deutscher Wetterdienst are loaded and cached successfully.', 'wetterwarner' ) . '</p>',
			'actions'     => '',
			'test'        => 'wetterwarner',
		);

		$uploads = wp_upload_dir( null, false );
		if ( ! wp_is_writable( $uploads['basedir'] ) ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Wetterwarner cannot store warning maps', 'wetterwarner' );
			$result['description'] = '<p>' . esc_html__( 'The uploads directory is not writable. Warning maps cannot be displayed.', 'wetterwarner' ) . '</p>';
		}

		$status  = Source::status();
		// Ein erfolgreicher Abruf entfernt "error" – ist es gesetzt, schlug der letzte Versuch fehl.
		$failing = static function ( $source ) use ( $status ) {
			return ! empty( $status[ $source ]['error'] );
		};

		if ( $failing( 'api' ) ) {
			if ( isset( $status['districts']['ok'] ) && ! $failing( 'districts' ) ) {
				$result['status']      = 'recommended';
				$result['label']       = __( 'Wetterwarner uses the direct DWD fallback', 'wetterwarner' );
				$result['description'] = '<p>' . esc_html__( 'The Wetterwarner API is currently unreachable. Alerts are loaded directly from the Deutscher Wetterdienst.', 'wetterwarner' ) . '</p><p><small>' . esc_html( $status['api']['error'] ) . '</small></p>';
			} else {
				$result['status']      = 'critical';
				$result['label']       = __( 'Wetterwarner cannot reach the Deutscher Wetterdienst', 'wetterwarner' );
				$result['description'] = '<p>' . esc_html( $status['api']['error'] ) . '</p>';
			}
		}

		if ( 'good' !== $result['status'] ) {
			$result['badge']['color'] = 'red';
		}
		return $result;
	}

	public static function debug_information( $info ) {
		$status = Source::status();
		$map    = Map::upload_dir();
		$fields = array(
			'version'   => array(
				'label' => __( 'Version', 'wetterwarner' ),
				'value' => WETTERWARNER_VERSION,
			),
			'map_dir'   => array(
				'label' => __( 'Map directory writable', 'wetterwarner' ),
				'value' => wp_is_writable( dirname( $map['path'] ) ) ? 'true' : 'false',
			),
			'cron'      => array(
				'label' => __( 'Next background update', 'wetterwarner' ),
				'value' => wp_next_scheduled( Plugin::CRON_HOOK ) ? wp_date( 'c', wp_next_scheduled( Plugin::CRON_HOOK ) ) : 'false',
			),
			'regions'   => array(
				'label' => __( 'Regions in use', 'wetterwarner' ),
				'value' => implode( ', ', array_keys( Plugin::usage()['regions'] ) ),
			),
		);
		foreach ( $status as $source => $entry ) {
			$fields[ 'status_' . $source ] = array(
				'label' => $source,
				'value' => ( ! empty( $entry['ok'] ) ? 'ok ' . wp_date( 'c', $entry['ok'] ) : '' ) . ( ! empty( $entry['error'] ) ? ' | error: ' . $entry['error'] : '' ),
			);
		}
		foreach ( self::colors() as $stage => $color ) {
			$fields[ 'color_' . $stage ] = array(
				/* translators: %d: warning level */
				'label' => sprintf( __( 'Background color level %d', 'wetterwarner' ), $stage ),
				'value' => $color,
			);
		}

		$info['wetterwarner'] = array(
			'label'  => 'Wetterwarner',
			'fields' => $fields,
		);
		return $info;
	}
}
