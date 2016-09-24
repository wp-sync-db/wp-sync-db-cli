<?php

/**
 * Sync your DB using WP Sync DB.
 */
class WPSDBCLI extends WP_CLI_Command {

	/**
	 * Run a syncing.
	 *
	 * ## OPTIONS
	 *
	 * <profile>
	 * : ID of the profile to use for the syncing.
	 *
	 * ## EXAMPLES
	 *
	 * 	wp wpsdb sync 1
	 *
	 * @synopsis <profile>
	 *
	 * @since 1.0
	 */
	public function sync( $args, $assoc_args ) {
		$profile = $args[0];

		$result = wpsdb_sync( $profile );

		if ( true === $result ) {
			WP_CLI::success( __( 'Syncing successful.', 'wp-sync-db-cli' ) );
			return;
		}

		WP_CLI::warning( $result->get_error_message() );
		return;
	}

}

WP_CLI::add_command( 'wpsdb', 'WPSDBCLI' );
