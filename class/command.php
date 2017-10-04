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
	 * Create a profile
	 *
	 * ## OPTIONS
	 *
	 * <profile>
	 * : ID of the profile to use for the migration.
	 *
	 * ## EXAMPLES
	 *
	 * 	wp wpsdb create-profile 1
	 *
	 * @synopsis <profile>
	 *
	 * @since 1.0
	 */
	/**
		* @subcommand create-profile
		*/
	public function create_profile( $args, $assoc_args ) {
		$name = $args[0];
		$result = wpdsb_create_profile( $name, $assoc_args );

		if ( true === $result ) {
			WP_CLI::success( __( 'Profile created.', 'wp-sync-db-cli' ) );
			return;
		}

		WP_CLI::error( $result->get_error_message() );
		return;
	}

}

WP_CLI::add_command( 'wpsdb', 'WPSDBCLI' );
