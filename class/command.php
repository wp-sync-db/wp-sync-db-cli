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
		$manual_profile = [];

		// Target manually (maybe no database available yet)
		if ($assoc_args['connection-info'] && $assoc_args['action']) {
			$manual_profile = array(
				'connection_info' 		=> $assoc_args['connection-info'],
				'action' 				=> $assoc_args['action'],
				'create_backup' 		=> $assoc_args['create_backup'],
				'backup_option' 		=> null,
				'prefixed_tables' 		=> null,
				'select_backup' 		=> null,
				'table_migrate_option' 	=> null,
				'select_tables' 		=> null,
			);
		}

		$result = wpsdb_migrate( $profile, $manual_profile );

		if ( true === $result ) {
			WP_CLI::success( __( 'Migration successful.', 'wp-sync-db-cli' ) );
			return;
		}

		WP_CLI::warning( $result->get_error_message() );
		return;
	}

}

WP_CLI::add_command( 'wpsdb', 'WPSDBCLI' );
