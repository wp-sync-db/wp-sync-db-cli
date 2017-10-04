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
	 * : Name of the new profile to create
	 *
	 * [--remote-wordpress=<value>]
	 * : Remote WordPress location to migrate database from.
	 *
	 * This is the first part the whole token you are told to copy from
	 * the /wp-admin/ backend of Migrate DB under Settings, before the newline.
	 *
	 * [--token=<value>]
	 * : Token from WordPress location to migrate database from.
	 *
	 * This is the second part the whole token you are told to copy from
	 * the /wp-admin/ backend of Migrate DB under Settings, after the newline.
	 *
	 * [--migrate-tables=<CSV of tables to migrate>]
	 * : Comma separated list of tables to migrate from the remote WordPress to locally.
	 *
	 * If you set this to `outlandish` it will migrate the tables we would typically expect from WordPress.
	 *
	 * [--exclude-post-types=<CSV of tables to migrate>]
	 * : Comma separated list of post types to exclude from migrate from the remote WordPress to locally.
	 *
	 * [--<WP-Migrate-Profile-Option-As-Kebab-Case>=<true|false>]
	 * : Set any Migrate DB option in this profile.
	 *
	 * ## EXAMPLES
	 *
	 * 	wp wpsdb create-profile Staging --remote-wordpress https://wordpress.example \
	 * 																	--token CUOu2t5kaVienGLUxAGhN4bvWh1FXqJA
	 *
	 *  Setup a profile with the defaults called Staging.
	 *
	 *
	 * 	wp wpsdb create-profile Staging --remote-wordpress https://wordpress.example \
	 * 																	--token CUOu2t5kaVienGLUxAGhN4bvWh1FXqJA \
	 *                  								 --migrate-tables=wp_posts
	 *
	 *  Setup a profile with the defaults called Staging that only migrates the wp_posts table.
	 *
	 *
	 * 	wp wpsdb create-profile Staging --remote-wordpress https://wordpress.example \
	 * 																	--token CUOu2t5kaVienGLUxAGhN4bvWh1FXqJA \
	 *                  								 --exclude-post-types=page
	 *
	 *  Setup a profile with the defaults called Staging that excludes WordPress page post types.
	 *
	 *
	 * 	wp wpsdb create-profile Staging --remote-wordpress https://wordpress.example \
	 * 																	--token CUOu2t5kaVienGLUxAGhN4bvWh1FXqJA \
	 *                  								 --create_backup=true
	 *
	 *  Setup a profile with the defaults called Staging that creates backups when migrations are run.
	 *
	 * @synopsis <profile>
	 *
	 * @since 1.0
	 * @subcommand create-profile
	 */
	public function create_profile( $args, $assoc_args ) {
		$name = $args[0];

		if ( ! isset( $assoc_args['remote-wordpress'] ) || ! isset( $assoc_args['token'] ) ) {
			$error_lines = array();
			$error_lines[] = __( 'Connection information to remote WordPress installation for migration is required.' );
			$error_lines[] = __( 'Set with --remote-wordpress and --token flag.' );
			WP_CLI::error_multi_line( $error_lines );
			return;
		}

		$result = wpdsb_create_profile( $name, $assoc_args );

		if ( true === $result ) {
			WP_CLI::success( sprintf( __( 'Profile %1$s created.', 'wp-sync-db-cli' ), $name ) );
			return;
		}

		WP_CLI::error( $result->get_error_message() );
		return;
	}

}

WP_CLI::add_command( 'wpsdb', 'WPSDBCLI' );
