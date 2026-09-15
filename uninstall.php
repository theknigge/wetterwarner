<?php
/**
 * Entfernt alle Daten des Plugins.
 *
 * @package Wetterwarner
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

foreach ( array( 'wetterwarner_settings', 'wetterwarner_version', 'wetterwarner_usage', 'wetterwarner_status', 'wetterwarner_store', 'wetterwarner_regions', 'wetterwarner_migration', 'widget_wetterwarner_widget' ) as $option ) {
	delete_option( $option );
}

$wetterwarner_like = $wpdb->esc_like( '_transient_wetterwarner_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wetterwarner_like, str_replace( '_transient_', '_transient_timeout_', $wetterwarner_like ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

wp_clear_scheduled_hook( 'wetterwarner_refresh' );
wp_clear_scheduled_hook( 'wetterwarner_data_update' );

$wetterwarner_uploads = wp_upload_dir( null, false );
$wetterwarner_dir     = $wetterwarner_uploads['basedir'] . '/wetterwarner';
if ( is_dir( $wetterwarner_dir ) ) {
	foreach ( (array) glob( $wetterwarner_dir . '/*' ) as $wetterwarner_file ) {
		wp_delete_file( $wetterwarner_file );
	}
	rmdir( $wetterwarner_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
