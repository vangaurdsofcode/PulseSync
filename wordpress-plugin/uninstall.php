<?php
/**
 * Remove plugin data when PulseSync Connector is uninstalled.
 *
 * @package PulseSync
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

wp_clear_scheduled_hook( 'pulsesync_retry_queue' );
$table_name = $wpdb->prefix . 'pulsesync_queue';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall removes the plugin-owned queue table.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
delete_option( 'pulsesync_settings' );
delete_site_option( 'pulsesync_settings' );
