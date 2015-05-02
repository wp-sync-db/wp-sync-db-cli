<?php
/*
Plugin Name: WP Migrate DB Pro CLI
Plugin URI: http://deliciousbrains.com/wp-migrate-db-pro/
Description: An extension to WP Migrate DB Pro, allows you to execute migrations using a function call or via WP-CLI
Author: Delicious Brains
Version: 1.1
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
	// register with wp-cli if it's running, and command hasn't already been defined elsewhere
	if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'WPMDBCLI' ) ) {
		require_once dirname( __FILE__ ) . '/class/command.php';
	}

	load_plugin_textdomain( 'wp-migrate-db-pro-cli', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'wp_migrate_db_pro_cli_loaded', 20 );

function wpmdb_migrate( $profile ) {
	$wpmdbpro_cli = wp_migrate_db_pro_cli();
	if ( empty( $wpmdbpro_cli ) ) {
		return new WP_Error( 'wpmdb_cli_error', __( 'WP Migrate DB Pro CLI class not available', 'wp-migrate-db-pro-cli' ) );
	}
	return $wpmdbpro_cli->cli_migration( $profile );
}

/**
 * Populate the $wpmdbpro_cli global with an instance of the WPMDBPro_CLI class and return it.
 *
 * @return WPMDBPro_CLI The one true global instance of the WPMDBPro_CLI class.
 */
function wp_migrate_db_pro_cli() {
	global $wpmdbpro_cli;

	if ( ! is_null( $wpmdbpro_cli ) ) {
		return $wpmdbpro_cli;
	}

	if ( function_exists( 'wp_migrate_db_pro' ) ) {
		wp_migrate_db_pro();
	} else {
		return false;
	}

	do_action( 'wp_migrate_db_pro_cli_before_load' );

	require_once dirname( __FILE__ ) . '/class/wpmdbpro-cli.php';
	$wpmdbpro_cli = new WPMDBPro_CLI( __FILE__ );

	do_action( 'wp_migrate_db_pro_cli_after_load' );

	return $wpmdbpro_cli;
}
