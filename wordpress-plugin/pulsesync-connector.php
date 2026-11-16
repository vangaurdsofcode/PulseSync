<?php
/**
 * Plugin Name: PulseSync Connector
 * Description: Capture lightweight leads and sync WordPress events to PulseSync.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: MIT
 * Text Domain: pulsesync-connector
 *
 * @package PulseSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PULSESYNC_VERSION', '1.0.0' );
define( 'PULSESYNC_PLUGIN_FILE', __FILE__ );
define( 'PULSESYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PULSESYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PULSESYNC_PLUGIN_DIR . 'src/class-plugin.php';

register_activation_hook( __FILE__, array( 'PulseSync\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PulseSync\\Plugin', 'deactivate' ) );

( new PulseSync\Plugin() )->register();
