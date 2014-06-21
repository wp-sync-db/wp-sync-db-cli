<?php
/*
Plugin Name: WP Sync DB CLI
GitHub Plugin URI: slang800/wp-sync-db-cli
Description: An extension to WP Sync DB, allows you to execute migrations using a function call or via WP-CLI
Author: Sean Lang
Version: 1.0b1
Author URI: http://slang.cx
Network: True
*/

require_once 'version.php';
$GLOBALS['wpsdb_meta']['wp-sync-db-cli']['folder'] = basename( plugin_dir_path( __FILE__ ) );

function wp_sync_db_cli_loaded() {
	if ( ! class_exists( 'WPSDB_Addon' ) ) return;

	require_once __DIR__ . '/class/wpsdb-cli.php';

	// register with wp-cli if it's running, and command hasn't already been defined elsewhere
	if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'WPSDBCLI' ) ) {
		require_once __DIR__ . '/class/command.php';
	}

	load_plugin_textdomain( 'wp-sync-db-cli', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	global $wpsdb_cli;
	$wpsdb_cli = new WPSDB_CLI( __FILE__ );
}
add_action( 'plugins_loaded', 'wp_sync_db_cli_loaded', 20 );

function wpsdb_migrate( $profile ) {
	global $wpsdb_cli;
	if( empty( $wpsdb_cli ) ) {
		return new WP_Error( 'wpsdb_cli_error', __( 'WP Sync DB CLI class not available', 'wp-sync-db-cli' ) );
	}
	return $wpsdb_cli->cli_migration( $profile );
}
