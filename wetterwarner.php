<?php
/**
 * Plugin Name:       Wetterwarner
 * Plugin URI:        https://it93.de/projekte/wetterwarner/
 * Description:       Zeigt amtliche Wetterwarnungen des Deutschen Wetterdienstes als Block, Shortcode oder Widget an.
 * Version:           3.0.0
 * Author:            Tim Knigge
 * Author URI:        https://wetterwarner.de/
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Tested up to:      7.0
 * Text Domain:       wetterwarner
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'WETTERWARNER_VERSION', '3.0.0' );
define( 'WETTERWARNER_FILE', __FILE__ );
define( 'WETTERWARNER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WETTERWARNER_URL', plugin_dir_url( __FILE__ ) );

require_once WETTERWARNER_DIR . 'includes/class-regions.php';
require_once WETTERWARNER_DIR . 'includes/class-source.php';
require_once WETTERWARNER_DIR . 'includes/class-map.php';
require_once WETTERWARNER_DIR . 'includes/class-renderer.php';
require_once WETTERWARNER_DIR . 'includes/class-rest.php';
require_once WETTERWARNER_DIR . 'includes/class-widget.php';
require_once WETTERWARNER_DIR . 'includes/class-admin.php';
require_once WETTERWARNER_DIR . 'includes/class-telemetry.php';
require_once WETTERWARNER_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Wetterwarner\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Wetterwarner\\Plugin', 'deactivate' ) );

Wetterwarner\Plugin::init();
