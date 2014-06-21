<?php
/*
Plugin Name: WP Migrate DB Pro CLI
Plugin URI: http://deliciousbrains.com/wp-migrate-db-pro/
Description: An extension to WP Migrate DB Pro, allows you to execute migrations using a function call or via WP-CLI
Author: Delicious Brains
Version: 1.0b1
Author URI: http://deliciousbrains.com
Network: True
*/

// Copyright (c) 2013 Delicious Brains. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

require_once 'version.php';
$GLOBALS['wpmdb_meta']['wp-migrate-db-pro-cli']['folder'] = basename( plugin_dir_path( __FILE__ ) );

function wp_migrate_db_pro_cli_loaded() {
	if ( ! class_exists( 'WPMDBPro_Addon' ) ) return;

	require_once __DIR__ . '/class/wpmdbpro-cli.php';

	// register with wp-cli if it's running, and command hasn't already been defined elsewhere
	if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'WPMDBCLI' ) ) {
		require_once __DIR__ . '/class/command.php';
	}

	load_plugin_textdomain( 'wp-migrate-db-pro-cli', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	global $wpmdbpro_cli;
	$wpmdbpro_cli = new WPMDBPro_CLI( __FILE__ );
}
add_action( 'plugins_loaded', 'wp_migrate_db_pro_cli_loaded', 20 );

function wpmdb_migrate( $profile ) {
	global $wpmdbpro_cli;
	if( empty( $wpmdbpro_cli ) ) {
		return new WP_Error( 'wpmdb_cli_error', __( 'WP Migrate DB Pro CLI class not available', 'wp-migrate-db-pro-cli' ) );
	}
	return $wpmdbpro_cli->cli_migration( $profile );
}