<?php
/**
 * Entfernt alle Daten des Plugins.
 *
 * @package Wetterwarner
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Freiwillige Nutzungsdaten bei der API löschen lassen.
if ( 'yes' === get_option( 'wetterwarner_telemetry' ) ) {
	$wetterwarner_api = defined( 'WETTERWARNER_API_URL' ) ? WETTERWARNER_API_URL : 'https://api.wetterwarner.de/v3/';
	if ( '' !== $wetterwarner_api ) {
		$wetterwarner_home = home_url();
		$wetterwarner_path = trim( (string) wp_parse_url( $wetterwarner_home, PHP_URL_PATH ), '/' );
		wp_remote_post(
			trailingslashit( $wetterwarner_api ) . 'sites/forget',
			array(
				'timeout' => 5,
				'headers' => array( 'X-Wetterwarner-Site' => strtolower( wp_parse_url( $wetterwarner_home, PHP_URL_HOST ) . ( '' !== $wetterwarner_path ? '/' . $wetterwarner_path : '' ) ) ),
			)
		);
	}
}

foreach ( array( 'wetterwarner_settings', 'wetterwarner_version', 'wetterwarner_usage', 'wetterwarner_status', 'wetterwarner_store', 'wetterwarner_regions', 'wetterwarner_migration', 'wetterwarner_telemetry', 'widget_wetterwarner_widget' ) as $wetterwarner_option ) {
	delete_option( $wetterwarner_option );
}

$wetterwarner_like = $wpdb->esc_like( '_transient_wetterwarner_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wetterwarner_like, str_replace( '_transient_', '_transient_timeout_', $wetterwarner_like ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

wp_unschedule_hook( 'wetterwarner_refresh' );
wp_unschedule_hook( 'wetterwarner_data_update' );

$wetterwarner_uploads = wp_upload_dir( null, false );
$wetterwarner_dir     = $wetterwarner_uploads['basedir'] . '/wetterwarner';
if ( is_dir( $wetterwarner_dir ) ) {
	foreach ( (array) glob( $wetterwarner_dir . '/*' ) as $wetterwarner_file ) {
		wp_delete_file( $wetterwarner_file );
	}
	rmdir( $wetterwarner_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
