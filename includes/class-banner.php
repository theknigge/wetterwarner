<?php
/**
 * Warnbanner: zeigt aktuelle Warnungen einer Region auf jeder Seite.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

defined( 'ABSPATH' ) || exit;

class Banner {

	const OPTION = 'wetterwarner_banner';
	const GROUP  = 'wetterwarner_banner';
	const STYLE  = 'wetterwarner-banner';

	/** Wurde das Banner auf dieser Seite schon ausgegeben? */
	private static $printed = false;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'body_open' ) );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), 5 );
	}

	public static function settings() {
		return array_merge(
			array(
				'enabled'     => false,
				'regionId'    => '',
				'minStage'    => 1,
				'position'    => 'top',
				'dismissible' => true,
				'url'         => '',
			),
			(array) get_option( self::OPTION, array() )
		);
	}

	public static function active() {
		$settings = self::settings();
		return $settings['enabled'] && '' !== $settings['regionId'];
	}

	public static function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public static function sanitize( $input ) {
		$input  = (array) $input;
		$region = isset( $input['regionId'] ) ? sanitize_text_field( $input['regionId'] ) : '';
		return array(
			'enabled'     => ! empty( $input['enabled'] ),
			'regionId'    => Regions::DEMO === $region || Regions::is_valid_id( $region ) ? $region : '',
			'minStage'    => max( 1, min( 4, isset( $input['minStage'] ) ? (int) $input['minStage'] : 1 ) ),
			'position'    => isset( $input['position'] ) && 'bottom' === $input['position'] ? 'bottom' : 'top',
			'dismissible' => ! empty( $input['dismissible'] ),
			'url'         => isset( $input['url'] ) ? esc_url_raw( trim( $input['url'] ) ) : '',
		);
	}

	public static function enqueue() {
		if ( ! self::active() ) {
			return;
		}
		wp_register_style( self::STYLE, false, array(), WETTERWARNER_VERSION );
		wp_enqueue_style( self::STYLE );
		wp_add_inline_style(
			self::STYLE,
			'.wetterwarner-banner{box-sizing:border-box;display:flex;gap:.75em;align-items:center;width:100%;padding:.6em 1em;font-size:.95rem;line-height:1.4;text-align:left}'
			. '.wetterwarner-banner[hidden]{display:none}'
			. '.wetterwarner-banner--stage-1{background:#ffeb3b;color:#000}.wetterwarner-banner--stage-2{background:#fb8c00;color:#000}'
			. '.wetterwarner-banner--stage-3{background:#c62828;color:#fff}.wetterwarner-banner--stage-4{background:#880e4f;color:#fff}'
			. '.wetterwarner-banner--bottom,.wetterwarner-banner--floating{position:fixed;left:0;right:0;z-index:99990}'
			. '.wetterwarner-banner--bottom{bottom:0}.wetterwarner-banner--floating{top:0}.admin-bar .wetterwarner-banner--floating{top:32px}'
			. '.wetterwarner-banner__icon{flex:none;display:flex}.wetterwarner-banner__text{flex:1;min-width:0;margin:0}'
			. '.wetterwarner-banner a{color:inherit;text-decoration:underline}.wetterwarner-banner__source{font-size:.8em;opacity:.85}'
			. '.wetterwarner-banner__close{flex:none;border:0;background:none;color:inherit;font-size:1.5em;line-height:1;cursor:pointer;padding:0 .25em}'
		);
		// Auch ohne Nachladen nötig: Schließen-Knopf.
		Live::enqueue_script();
	}

	public static function body_open() {
		if ( 'top' === self::settings()['position'] ) {
			self::output( false );
		}
	}

	/** Position unten – und Rückfall für Themes ohne wp_body_open(). */
	public static function footer() {
		self::output( 'top' === self::settings()['position'] );
	}

	private static function output( $floating ) {
		if ( self::$printed || ! self::active() || is_embed() ) {
			return;
		}
		self::$printed = true;
		echo self::html( $floating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapt selbst.
	}

	/**
	 * Banner oder – ohne passende Warnung – ein unsichtbarer Platzhalter zum Nachladen.
	 *
	 * @param bool $floating Fest am oberen Rand (Theme ohne wp_body_open). Beim
	 *                       Nachladen übernimmt das Skript die Angabe von der Seite.
	 */
	public static function html( $floating = false ) {
		if ( ! self::active() ) {
			return '';
		}
		$settings = self::settings();
		$region   = Regions::get( $settings['regionId'] );
		if ( ! $region ) {
			return '';
		}
		Plugin::track_usage( $region['id'] );

		$warnings = Source::get_warnings( $region['id'] );
		// Keine Vorabinformationen: Das Banner meldet nur gültige Warnungen.
		$warnings = is_wp_error( $warnings ) ? null : Renderer::filter(
			$warnings,
			array(
				'minStage'   => $settings['minStage'],
				'eventTypes' => '',
				'showPrior'  => false,
			)
		);

		$live = Live::attributes( array( 'banner' => 1 ), null === $warnings ? 0 : Source::fetched_at( $region['id'] ) );

		if ( ! $warnings ) {
			return $live ? '<div hidden data-ww-empty="1"' . ( $floating ? ' data-ww-floating="1"' : '' ) . $live . '></div>' : '';
		}

		// Sortierung aus Source: höchste Stufe zuerst.
		$first = $warnings[0];
		$more  = count( $warnings ) - 1;
		$key   = md5(
			implode(
				'|',
				array_map(
					static function ( $w ) {
						return $w['event'] . $w['stage'] . $w['start'];
					},
					$warnings
				)
			)
		);

		$classes = 'wetterwarner-banner wetterwarner-banner--stage-' . (int) $first['stage'];
		if ( 'bottom' === $settings['position'] ) {
			$classes .= ' wetterwarner-banner--bottom';
		} elseif ( $floating ) {
			$classes .= ' wetterwarner-banner--floating';
		}

		$text = '<strong>' . esc_html( $first['event'] ) . '</strong> – ' . esc_html( $region['name'] ) . ' · ' . esc_html( Renderer::validity( $first ) );
		if ( $more > 0 ) {
			/* translators: %d: number of further warnings */
			$text .= ' · ' . esc_html( sprintf( __( '+%d more', 'wetterwarner' ), $more ) );
		}
		$text .= ' · <a href="' . esc_url( $settings['url'] ? $settings['url'] : Renderer::DWD_URL ) . '">' . esc_html__( 'Details', 'wetterwarner' ) . '</a>';
		// Pflichtangabe (CC BY 4.0).
		/* translators: %s: link to Deutscher Wetterdienst */
		$text .= ' <span class="wetterwarner-banner__source">' . sprintf( esc_html__( 'Source: %s', 'wetterwarner' ), 'DWD' ) . '</span>';

		$close = $settings['dismissible']
			? '<button type="button" class="wetterwarner-banner__close" aria-label="' . esc_attr__( 'Close banner', 'wetterwarner' ) . '">&times;</button>'
			: '';

		return '<div class="' . esc_attr( $classes ) . '" role="region" aria-label="' . esc_attr__( 'Weather alerts', 'wetterwarner' ) . '" data-ww-key="' . esc_attr( $key ) . '"' . ( $floating ? ' data-ww-floating="1"' : '' ) . $live . '>'
			. '<span class="wetterwarner-banner__icon">' . Icons::svg( $first['type'] ) . '</span>'
			. '<p class="wetterwarner-banner__text">' . $text . '</p>' . $close . '</div>';
	}

	/** Eigener Abschnitt auf der Einstellungsseite. */
	public static function render_settings() {
		$s      = self::settings();
		$region = $s['regionId'] ? Regions::get( $s['regionId'] ) : null;
		$label  = $region ? Rest::prepare( $region )['label'] : '';
		$name   = self::OPTION;
		?>
		<h2 id="wetterwarner-banner"><?php esc_html_e( 'Warning banner', 'wetterwarner' ); ?></h2>
		<p><?php esc_html_e( 'Shows current warnings for one region at the top or bottom of every page. Without a warning, nothing is displayed.', 'wetterwarner' ); ?></p>
		<form action="options.php" method="post">
			<?php settings_fields( self::GROUP ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Banner', 'wetterwarner' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Show warning banner', 'wetterwarner' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="wetterwarner-banner-region"><?php esc_html_e( 'Warning region', 'wetterwarner' ); ?></label></th>
					<td>
						<div class="wetterwarner-region" style="max-width:25rem">
							<input type="search" class="regular-text wetterwarner-region-search" id="wetterwarner-banner-region" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Search town or district …', 'wetterwarner' ); ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="wetterwarner-banner-results">
							<input type="hidden" class="wetterwarner-region-id" name="<?php echo esc_attr( $name ); ?>[regionId]" value="<?php echo esc_attr( $s['regionId'] ); ?>">
							<ul class="wetterwarner-region-results" id="wetterwarner-banner-results" role="listbox" hidden></ul>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wetterwarner-banner-stage"><?php esc_html_e( 'Minimum warning level', 'wetterwarner' ); ?></label></th>
					<td>
						<select id="wetterwarner-banner-stage" name="<?php echo esc_attr( $name ); ?>[minStage]">
							<?php foreach ( Renderer::stage_labels() as $value => $text ) : ?>
								<option value="<?php echo (int) $value; ?>" <?php selected( $s['minStage'], $value ); ?>><?php echo esc_html( $text ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wetterwarner-banner-position"><?php esc_html_e( 'Position', 'wetterwarner' ); ?></label></th>
					<td>
						<select id="wetterwarner-banner-position" name="<?php echo esc_attr( $name ); ?>[position]">
							<option value="top" <?php selected( $s['position'], 'top' ); ?>><?php esc_html_e( 'Top', 'wetterwarner' ); ?></option>
							<option value="bottom" <?php selected( $s['position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'wetterwarner' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Closing', 'wetterwarner' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[dismissible]" value="1" <?php checked( $s['dismissible'] ); ?>> <?php esc_html_e( 'Visitors can close the banner until the warnings change', 'wetterwarner' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="wetterwarner-banner-url"><?php esc_html_e( 'Link for "Details"', 'wetterwarner' ); ?></label></th>
					<td>
						<input type="url" class="regular-text" id="wetterwarner-banner-url" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $s['url'] ); ?>">
						<p class="description"><?php esc_html_e( 'For example your own page with the Wetterwarner block. Empty: dwd.de', 'wetterwarner' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save banner', 'wetterwarner' ) ); ?>
		</form>
		<?php
	}
}
