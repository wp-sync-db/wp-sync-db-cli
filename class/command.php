<?php

/**
 * Migrate your DB using WP Migrate DB Pro.
 */
class WPMDBCLI extends WP_CLI_Command {

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
	 * 	wp wpmdb migrate 1
	 *
	 * @synopsis <profile>
	 *
	 * @since 1.0
	 */
	public function migrate( $args, $assoc_args ) {
		$profile = $args[0];

		$result = wpmdb_migrate( $profile );

		if ( true === $result ) {
			WP_CLI::success( __( 'Migration successful.', 'wp-migrate-db-pro-cli' ) );
			return;
		}

		WP_CLI::warning( $result->get_error_message() );
		return;
	}

}

WP_CLI::add_command( 'wpmdb', 'WPMDBCLI' );