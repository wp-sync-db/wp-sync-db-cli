<?php

/**
 * Migrate your DB using WP Sync DB.
 */
class WPSDBCLI extends WP_CLI_Command {

	/**
	 * Run a migration.
	 *
	 * ## OPTIONS
	 *
	 * <profile>
	 * : ID of the profile to use for the migration.
	 *
	 * ## EXAMPLES
	 *
	 * 	wp wpsdb migrate 1
	 *
	 * @synopsis <profile>
	 *
	 * @since 1.0
	 */
	public function migrate( $args, $assoc_args ) {
		$profile = $args[0];

		$result = wpsdb_migrate( $profile );

		if ( true === $result ) {
			WP_CLI::success( __( 'Migration successful.', 'wp-sync-db-cli' ) );
			return;
		}

		WP_CLI::warning( $result->get_error_message() );
		return;
	}

	/**
	 * List all profiles.
	 *
	 * ## EXAMPLES
	 *
	 * 	wp wpsdb profiles
	 *
	 */

	public function profiles() {
		$result = wpsdb_profiles();

		if ( $result ) {
			WP_CLI::log( __( $result, 'wp-sync-db-cli' ) );
			return;
		}

		WP_CLI::warning( __( 'You have no profiles.', 'wp-sync-db-cli' ) );
		return;
	}

}

WP_CLI::add_command( 'wpsdb', 'WPSDBCLI' );
