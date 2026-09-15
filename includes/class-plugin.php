<?php
/**
 * Hooks, Block, Shortcode, Cron und Migration.
 *
 * @package Wetterwarner
 */

namespace Wetterwarner;

defined( 'ABSPATH' ) || exit;

class Plugin {

	const CRON_HOOK        = 'wetterwarner_refresh';
	const CRON_SCHEDULE    = 'wetterwarner_5min';
	const USAGE_OPTION     = 'wetterwarner_usage';
	const VERSION_OPTION   = 'wetterwarner_version';
	const MIGRATION_OPTION = 'wetterwarner_migration';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'rest_api_init', array( Rest::class, 'register_routes' ) );
		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'refresh' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_shortcode( 'wetterwarner', array( __CLASS__, 'shortcode' ) );

		Admin::init();
	}

	public static function register_block() {
		// Mitgelieferte deutsche Übersetzung, solange kein Sprachpaket von translate.wordpress.org existiert.
		load_plugin_textdomain( 'wetterwarner', false, dirname( plugin_basename( WETTERWARNER_FILE ) ) . '/languages' );

		register_block_type( WETTERWARNER_DIR . 'build/wetterwarner' );

		wp_register_style( 'wetterwarner-warnings-style', false, array(), WETTERWARNER_VERSION ); // Nur Rückfall, falls der Block-Build fehlt.
		wp_add_inline_style( 'wetterwarner-warnings-style', Admin::color_css() );
		wp_set_script_translations( 'wetterwarner-warnings-editor-script', 'wetterwarner', WETTERWARNER_DIR . 'languages' );
	}

	public static function register_widget() {
		register_widget( Widget::class );
	}

	/**
	 * [wetterwarner region="103241000" map="60"]
	 *
	 * @param array|string $atts Shortcode-Attribute.
	 */
	public static function shortcode( $atts ) {
		$atts    = is_array( $atts ) ? $atts : array();
		$aliases = array(
			'region'          => 'regionId',
			'warncellid'      => 'regionId',
			'title'           => 'title',
			'intro'           => 'introText',
			'no_warnings'     => 'noWarningsText',
			'max'             => 'maxWarnings',
			'show_always'     => 'showAlways',
			'validity'        => 'showValidity',
			'details'         => 'showDetails',
			'icons'           => 'showIcons',
			'colors'          => 'showColors',
			'link'            => 'linkWarnings',
			'hide_duplicates' => 'hideDuplicates',
			'source'          => 'showSource',
			'map'             => 'mapSize',
			'map_region'      => 'mapRegion',
		);

		$mapped = array();
		foreach ( $atts as $key => $value ) {
			$key = strtolower( (string) $key );
			if ( isset( $aliases[ $key ] ) ) {
				$mapped[ $aliases[ $key ] ] = $value;
			}
		}

		return Renderer::render( $mapped, array( 'wrapper' => 'class="wetterwarner wetterwarner--shortcode"' ) );
	}

	/**
	 * Merkt sich genutzte Regionen und Karten, damit der Cron sie vorab lädt.
	 *
	 * @param string $region_id Warncell-ID.
	 * @param string $map_code  Kartencode oder leer.
	 */
	public static function track_usage( $region_id, $map_code = '' ) {
		$usage   = self::usage();
		$now     = time();
		$changed = false;

		foreach ( array( 'regions' => $region_id, 'maps' => $map_code ) as $group => $key ) {
			if ( '' === $key || Regions::DEMO === $key ) {
				continue;
			}
			if ( ! isset( $usage[ $group ][ $key ] ) || $now - $usage[ $group ][ $key ] > DAY_IN_SECONDS ) {
				$usage[ $group ][ $key ] = $now;
				$changed                 = true;
			}
		}

		if ( $changed ) {
			update_option( self::USAGE_OPTION, $usage, false );
		}
	}

	/**
	 * @return array{regions: array<string,int>, maps: array<string,int>}
	 */
	public static function usage() {
		$usage = get_option( self::USAGE_OPTION, array() );
		return array(
			'regions' => isset( $usage['regions'] ) ? (array) $usage['regions'] : array(),
			'maps'    => isset( $usage['maps'] ) ? (array) $usage['maps'] : array(),
		);
	}

	/**
	 * WP-Cron: Warnungen und Karten der zuletzt genutzten Regionen aktualisieren.
	 */
	public static function refresh() {
		$usage  = self::usage();
		$expire = time() - 14 * DAY_IN_SECONDS;

		foreach ( $usage as $group => $entries ) {
			$usage[ $group ] = array_filter(
				$entries,
				static function ( $time ) use ( $expire ) {
					return $time > $expire;
				}
			);
		}
		update_option( self::USAGE_OPTION, $usage, false );

		// Alle genutzten Regionen gemeinsam, höchstens alle 5 Minuten.
		Source::refresh( array_map( 'strval', array_keys( $usage['regions'] ) ) );
		foreach ( array_keys( $usage['maps'] ) as $code ) {
			Map::refresh( (string) $code );
		}
	}

	public static function cron_schedules( $schedules ) {
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes', 'wetterwarner' ),
		);
		return $schedules;
	}

	public static function activate() {
		self::maybe_upgrade();
		self::schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( 'wetterwarner_data_update' );
		Source::clear_cache();
		Map::clear_cache();
	}

	private static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Läuft bei jedem Seitenaufruf, arbeitet aber nur nach einem Update.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::VERSION_OPTION, '' );

		if ( $installed && version_compare( $installed, WETTERWARNER_VERSION, '>=' ) ) {
			return;
		}

		if ( ! $installed || version_compare( $installed, '3.0.0', '<' ) ) {
			self::migrate_from_2x();
		}

		update_option( self::VERSION_OPTION, WETTERWARNER_VERSION );
		self::schedule();
	}

	/**
	 * Übernimmt die Widget-Einstellungen aus Version 2.x (wettwarn.de Feed-IDs).
	 */
	private static function migrate_from_2x() {
		wp_clear_scheduled_hook( 'wetterwarner_data_update' );

		$instances = get_option( 'widget_wetterwarner_widget' );
		if ( ! is_array( $instances ) ) {
			return;
		}

		$states = array(
			'Deutschland'            => 'de',
			'Baden-Württemberg'      => 'baw',
			'Bayern'                 => 'bay',
			'Berlin'                 => 'bbb',
			'Brandenburg'            => 'bbb',
			'Bremen'                 => 'nib',
			'Hamburg'                => 'shh',
			'Hessen'                 => 'hes',
			'Mecklenburg-Vorpommern' => 'mvp',
			'Niedersachsen'          => 'nib',
			'Nordrhein-Westfalen'    => 'nrw',
			'Rheinland-Pfalz'        => 'rps',
			'Saarland'               => 'rps',
			'Sachsen'                => 'sac',
			'Sachsen-Anhalt'         => 'saa',
			'Schleswig-Holstein'     => 'shh',
			'Thüringen'              => 'thu',
		);
		$fields = array(
			'ww_widget_titel'        => 'title',
			'ww_einleitungstext'     => 'introText',
			'ww_hinweistext'         => 'noWarningsText',
			'ww_max_meldungen'       => 'maxWarnings',
			'ww_immer_zeigen'        => 'showAlways',
			'ww_gueltigkeit_zeigen'  => 'showValidity',
			'ww_tooltip_zeigen'      => 'showDetails',
			'ww_icons_zeigen'        => 'showIcons',
			'ww_hintergrundfarbe'    => 'showColors',
			'ww_meldungen_verlinken' => 'linkWarnings',
			'ww_doppelte_ausblenden' => 'hideDuplicates',
			'ww_quelle_zeigen'       => 'showSource',
			'ww_kartengroesse'       => 'mapSize',
		);

		$unmatched = array();

		foreach ( $instances as $number => $old ) {
			if ( ! is_array( $old ) || ! array_key_exists( 'ww_feed_id', $old ) ) {
				continue;
			}

			$new = array();
			foreach ( $fields as $from => $to ) {
				if ( isset( $old[ $from ] ) ) {
					$new[ $to ] = $old[ $from ];
				}
			}
			foreach ( array( 'showAlways', 'showValidity', 'showDetails', 'showIcons', 'showColors', 'linkWarnings', 'hideDuplicates', 'showSource' ) as $flag ) {
				$new[ $flag ] = ! empty( $new[ $flag ] );
			}

			$new['regionId']  = Regions::from_legacy_feed_id( $old['ww_feed_id'] );
			$new['mapRegion'] = isset( $old['ww_kartenbundesland'], $states[ $old['ww_kartenbundesland'] ] ) ? $states[ $old['ww_kartenbundesland'] ] : 'auto';

			if ( '' === $new['regionId'] ) {
				$unmatched[] = strtoupper( (string) $old['ww_feed_id'] );
			}

			$instances[ $number ] = Renderer::sanitize( $new );
		}

		update_option( 'widget_wetterwarner_widget', $instances );

		if ( $unmatched ) {
			update_option( self::MIGRATION_OPTION, array_values( array_unique( $unmatched ) ), false );
		}
	}
}
