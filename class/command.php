<?php

/**
 * Migrate your DB using WP Sync DB.
 */
class WPSDBCLI extends WP_CLI_Command
{

	/**
	 * Run a migration. Either profile id or connection-info + action are required.
	 *
	 * ## OPTIONS
	 *
	 * [--profile=<int>] 
	 * : ID of the profile to use for the migration.
	 * 
	 * [--connection-info=<string>]
	 * : Manual connection info for when a profile by the ID is not found. The above ID will be used to save a copy
	 * 
	 * [--action=<string>]
	 * : The type of action to perform against the target connection
	 * ---
	 * default: pull
	 * options:
	 *   - pull
	 *   - push
	 * ---
	 *
	 * [--create-backup=<bit>]
	 * : Whether to take a backup before running the action.
	 * ---
	 * default: 1
	 * options:
	 *   - 0
	 *   - 1
	 * ---
	 * 
	 * ## EXAMPLES
	 *
	 * 	wp wpsdb migrate --profile=1
	 * 	wp wpsdb migrate --connection-info=https://example.com\n6AvE1jnBHIZtITuNCXj2eZArNM8uqNXC --action=pull --create-backup=1
	 *
	 * @synopsis [--profile=<int>] [--connection-info=<string>] [--action=<string>] [--create-backup=<bit>]
	 *
	 * @since 1.0
	 */
	public function migrate($args, $assoc_args)
	{
		$profile = null;
		$manual_profile = [];

		// Target manually (maybe no database available yet)

		if ($assoc_args['profile']) {
			$profile = $assoc_args['profile'];
		}
		if ($assoc_args['connection-info'] && $assoc_args['action']) {
			// Preprocess some variables
			$connection_info = stripcslashes($assoc_args['connection-info']);
			$connection_info_segments = explode("\n", $connection_info);
			$friendly_name = preg_replace("(^https?://)", "", $connection_info_segments[0]);

			// Create a default profile, that will save afterwards
			$manual_profile = array(
				'connection_info' 		=> $connection_info,
				'action' 				=> $assoc_args['action'],
				'create_backup' 		=> $assoc_args['create-backup'],
				'backup_option' 		=> "backup_only_with_prefix",
				'select_backup' 		=> null,
				'select_tables' 		=> null,
				'table_migrate_option' 	=> "migrate_only_with_prefix",
				'exclude_transients'    => 1,
				'media_files'           => 1,
				'remove_local_media'    => 1,
				'save_migration_profile_option' => 0,
				'create_new_profile'    => $friendly_name,
				'name'                  => $friendly_name,
				'save_computer'         => 0,
				'gzip_file'             => 1,
				'replace_guids'         => 1,
				'exclude_spam'          => 0,
				'keep_active_plugins'   => 1,
				'exclude_post_types'    => 0
			);
		}

		if ($profile == null && empty($manual_profile)) {
			WP_CLI::warning(__('Either profile id or connection-info + action are required.', 'wp-sync-db-cli'));
			WP_CLI::log('Usage: wpsdb migrate [--profile=<int>] [--connection-info=<string>] [--action=<string>] [--create-backup=<bit>]');
			return;
		}

		$result = wpsdb_migrate($profile, $manual_profile);

		if (true === $result) {
			WP_CLI::success(__('Migration successful.', 'wp-sync-db-cli'));
			return;
		}

		WP_CLI::warning($result->get_error_message());
		return;
	}
}

WP_CLI::add_command('wpsdb', 'WPSDBCLI');
