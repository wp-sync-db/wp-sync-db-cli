<?php

/**
 * Migrate your DB using WP Migrate DB Pro.
 */
class WPMDBCLI extends WP_CLI_Command {

	/**
	 * Push local DB up to remote.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : The URL of the remote site. Should include the URL encoded basic
	 * authentication credentials (if required). e.g. http://user:password@example.com
	 *
	 *     Must include the WordPress directory if WordPress is stored in a subdirectory.
	 *     e.g. http://example.com/wp
	 *
	 * <secret-key>
	 * : The remote site's secret key.
	 *
	 * [--find=<strings>]
	 * : A comma separated list of strings to find when performing a string find
	 * and replace across the database.
	 *
	 *     Table names should be quoted as needed, i.e. when using a comma in the
	 *     find/replace string.
	 *
	 *     The --replace=<strings> argument should be used in conjunction to specify
	 *     the replace values for the strings found using this argument. The number
	 *     of strings specified in this argument should match the number passed into
	 *     --replace=<strings> argument.
	 *
	 *     If omitted, a set of 2 find and replace pairs will be performed by default:
	 *
	 *       1. Strings containing URLs referencing the source site will be replace
	 *          by the destination URL.
	 *
	 *       2. Strings containing root file paths referencing the source site will
	 *          be replaced by the destination root file path.
	 *
	 * [--replace=<strings>]
	 * : A comma separated list of replace value strings to implement when
	 * performing a string find & replace across the database.
	 *
	 *     Should be used in conjunction with the --find=<strings> argument, see it's
	 *     documentation for further explanation of the find & replace functionality.
	 *
	 * [--include-tables=<tables>]
	 * : The comma separated list of tables to migrate. Excluding this parameter
	 * will migrate all tables in your database that begin with your
	 * installation's table prefix, e.g. wp_.
	 *
	 * [--exclude-post-types=<post-types>]
	 * : A comma separated list of post types to exclude. Excluding this parameter
	 * will migrate all post types.
	 *
	 * [--skip-replace-guids]
	 * : Do not perform a find & replace on the guid column in the wp_posts table.
	 *
	 * [--exclude-spam]
	 * : Exclude spam comments.
	 *
	 * [--preserve-active-plugins]
	 * : Preserves the active_plugins option (which plugins are activated/deactivated).
	 *
	 * [--include-transients]
	 * : Include transients (temporary cached data).
	 *
	 * [--backup=<backup>]
	 * : Perform a backup of the destination site's database tables before replacing it.
	 *
	 *     Accepted values:
	 *
	 *     * prefix - Backup only tables that begin with your installation's
	 *                table prefix (e.g. wp_)
	 *     * selected - Backup only tables selected for migration (as in --include-tables)
	 *     * A comma separated list of the tables to backup.
	 *
	 *     Table names should be quoted as needed, i.e. when using a comma in the
	 *     find/replace string.
	 *
	 * [--media=<compare|compare-and-remove|remove-and-copy>]
	 * : Perform a migration of the media files. Requires the Media Files Addon.
	 *
	 *     Accepted values:
	 *
	 *     * compare - compares remote and local media files and only uploads
	 *                 those missing or updated
	 *     * compare-and-remove - compares remote and local media files and only
	 *                            uploads those missing or updated. Removes remote
	 *                            media files that are not found on the local site
	 *     * remove-and-copy - removes all remote media files and uploads all local
	 *                         media files (skips comparison)
	 *
	 * ## EXAMPLES
	 *
	 *     wp migratedb push http://bradt.ca LJPmq3t8h6uuN7aqQ3YSnt7C88Wzzv5BVPlgLbYE \
	 *        --find=http://dev.bradt.ca,/Users/bradt/home/bradt.ca
	 *        --replace=http://bradt.ca,/home/bradt.ca
	 *        --include-tables=wp_posts,wp_postmeta
	 *
	 *
	 * @since 1.1
	 */
	public function push( $args, $assoc_args ) {
		$assoc_args['action'] = 'push';

		$profile = $this->_get_profile_data_from_args( $args, $assoc_args );
		if ( is_wp_error( $profile ) ) {
			WP_CLI::error( $profile );
		}

		$this->_perform_cli_migration( $profile );
	}

	/**
	 * Pull remote DB down to local.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : The URL of the remote site. Should include the URL encoded basic
	 * authentication credentials (if required). e.g. http://user:password@example.com
	 *
	 *     Must include the WordPress directory if WordPress is stored in a subdirectory.
	 *     e.g. http://example.com/wp
	 *
	 * <secret-key>
	 * : The remote site's secret key.
	 *
	 * [--find=<strings>]
	 * : A comma separated list of strings to find when performing a string find
	 * and replace across the database.
	 *
	 *     Table names should be quoted as needed, i.e. when using a comma in the
	 *     find/replace string.
	 *
	 *     The --replace=<strings> argument should be used in conjunction to specify
	 *     the replace values for the strings found using this argument. The number
	 *     of strings specified in this argument should match the number passed into
	 *     --replace=<strings> argument.
	 *
	 *     If omitted, a set of 2 find and replace pairs will be performed by default:
	 *
	 *       1. Strings containing URLs referencing the source site will be replace
	 *          by the destination URL.
	 *
	 *       2. Strings containing root file paths referencing the source site will
	 *          be replaced by the destination root file path.
	 *
	 * [--replace=<strings>]
	 * : A comma separated list of replace value strings to implement when
	 * performing a string find & replace across the database.
	 *
	 *     Should be used in conjunction with the --find=<strings> argument, see it's
	 *     documentation for further explanation of the find & replace functionality.
	 *
	 * [--include-tables=<tables>]
	 * : The comma separated list of tables to migrate. Excluding this parameter
	 * will migrate all tables in your database that begin with your
	 * installation's table prefix, e.g. wp_.
	 *
	 * [--exclude-post-types=<post-types>]
	 * : A comma separated list of post types to exclude. Excluding this parameter
	 * will migrate all post types.
	 *
	 * [--skip-replace-guids]
	 * : Do not perform a find & replace on the guid column in the wp_posts table.
	 *
	 * [--exclude-spam]
	 * : Exclude spam comments.
	 *
	 * [--preserve-active-plugins]
	 * : Preserves the active_plugins option (which plugins are activated/deactivated).
	 *
	 * [--include-transients]
	 * : Include transients (temporary cached data).
	 *
	 * [--backup=<backup>]
	 * : Perform a backup of the destination site's database tables before replacing it.
	 *
	 *     Accepted values:
	 *
	 *     * prefix - Backup only tables that begin with your installation's
	 *                table prefix (e.g. wp_)
	 *     * selected - Backup only tables selected for migration (as in --include-tables)
	 *     * A comma separated list of the tables to backup.
	 *
	 *     Table names should be quoted as needed, i.e. when using a comma in the
	 *     find/replace string.
	 *
	 * [--media=<compare|compare-and-remove|remove-and-copy>]
	 * : Perform a migration of the media files. Requires the Media Files Addon.
	 *
	 *     Accepted values:
	 *
	 *     * compare - compares remote and local media files and only downloads
	 *                 those missing or updated
	 *     * compare-and-remove - compares remote and local media files and only
	 *                            downloads those missing or updated. Removes local
	 *                            media files that are not found on the remote site
	 *     * remove-and-copy - removes all local media files and downloads all remote
	 *                         media files (skips comparison)
	 *
	 * ## EXAMPLES
	 *
	 *     wp migratedb pull http://bradt.ca LJPmq3t8h6uuN7aqQ3YSnt7C88Wzzv5BVPlgLbYE \
	 *        --find=http://dev.bradt.ca,/Users/bradt/home/bradt.ca
	 *        --replace=http://bradt.ca,/home/bradt.ca
	 *        --include-tables=wp_posts,wp_postmeta
	 *
	 *
	 * @since 1.1
	 */
	public function pull( $args, $assoc_args ) {
		$assoc_args['action'] = 'pull';

		$profile = $this->_get_profile_data_from_args( $args, $assoc_args );
		if ( is_wp_error( $profile ) ) {
			WP_CLI::error( $profile );
		}

		$this->_perform_cli_migration( $profile );
	}

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
	 *  wp migratedb migrate 1
	 *
	 * @synopsis <profile>
	 *
	 * @since 1.0
	 */
	public function migrate( $args, $assoc_args ) {
		$profile = $args[0];

		$this->_perform_cli_migration( $profile );
	}

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
	 * 	wp migratedb profile 1
	 *
	 * @synopsis <profile>
	 *
	 * @since 1.1
	 */
	public function profile( $args, $assoc_args ) {
		// uses migrate method
		return $this->migrate( $args, $assoc_args );
	}

	/**
	 * Get profile data from CLI args.
	 *
	 * @since 1.1
	 *
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @return array|WP_Error
	 */
	protected function _get_profile_data_from_args( $args, $assoc_args ) {
		$wpmdbpro_cli = wp_migrate_db_pro_cli();

		if ( empty( $args[0] ) && empty( $args[1] ) ) {
			return $wpmdbpro_cli->cli_error( __( 'URL and secret-key is required', 'wp-migrate-db-pro-cli' ) );
		}
		$connection_info = sprintf( '%s %s', $args[0], $args[1] );

		if ( empty( $assoc_args['action'] ) ) {
			return $wpmdbpro_cli->cli_error( __( 'Missing action parameter', 'wp-migrate-db-pro-cli' ) );
		}
		$action = $assoc_args['action'];

		// --find=<old> and --replace=<new>
		$replace_old = array();
		$replace_new = array();
		if ( ! empty( $assoc_args['find'] ) ) {
			$replace_old = explode( ',', $assoc_args['find'] );
		}
		if ( ! empty( $assoc_args['replace'] ) ) {
			$replace_new = explode( ',', $assoc_args['replace'] );
		}
		if ( count( $replace_old ) !== count( $replace_new ) ) {
			return $wpmdbpro_cli->cli_error( sprintf( __( '%1$s and %2$s must contain the same number of values', 'wp-migrate-db-pro-cli' ), '--find', '--replace' ) );
		}
		array_unshift( $replace_old, '' );
		array_unshift( $replace_new, '' );

		// --include-tables=<tables>
		if ( ! empty( $assoc_args['include-tables'] ) ) {
			$table_migrate_option = 'migrate_select';
			$select_tables        = explode( ',' , $assoc_args['include-tables'] );
		} else {
			$select_tables        = array();
			$table_migrate_option = 'migrate_only_with_prefix';
		}

		// --exclude-post-types=<post-types>
		$select_post_types = array();
		if ( ! empty( $assoc_args['exclude-post-types'] ) ) {
			$select_post_types = explode( ',', $assoc_args['exclude-post-types'] );
		}
		$exclude_post_types = count( $select_post_types ) > 0 ? 1 : 0;

		// --skip-replace-guids
		$replace_guids = 1;
		if ( isset( $assoc_args['skip-replace-guids'] ) ) {
			$replace_guids = 0;
		}

		// --exclude-spam
		$exclude_spam = intval( isset( $assoc_args['exclude-spam'] ) );

		// --preserve-active-plugins
		$keep_active_plugins = intval( isset( $assoc_args['preserve-active-plugins'] ) );

		// --include-transients.
		$exclude_transients = intval( ! isset( $assoc_args['include-transients'] ) );

		// --backup.
		$create_backup = 0;
		$backup_option = 'backup_only_with_prefix';
		$select_backup = array( '' );
		if ( ! empty( $assoc_args['backup'] ) ) {
			$create_backup = 1;
			if ( ! in_array( $assoc_args['backup'], array( 'prefix', 'selected' ) ) ) {
				$backup_option = 'backup_manual_select';
				$select_backup = explode( ',', $assoc_args['backup'] );
			} else if ( 'selected' === $assoc_args['backup'] ) {
				$backup_option = 'backup_selected';
			}
		}

		// --media
		$media_vars = array();
		if ( ! empty( $assoc_args['media'] ) ) {
			if ( ! class_exists( 'WPMDBPro_Media_Files' ) ) {
				return $wpmdbpro_cli->cli_error( __( 'The Media Files addon needs to be installed and activated to make use of this option', 'wp-migrate-db-pro-cli' ) );
			} else {
				$media_files            = 1;
				$remove_local_media     = 0;
				$media_migration_option = ( 'remove-and-copy' == $assoc_args['media'] ) ? 'entire' : 'compare';

				if ( 'compare-and-remove' == $assoc_args['media'] ) {
					$remove_local_media = 1;
				}

				$media_vars = array( 'media_files', 'media_migration_option', 'remove_local_media' );
			}
		}

		return compact(
			'connection_info',
			'action',
			'replace_old',
			'replace_new',
			'table_migrate_option',
			'select_tables',
			'exclude_post_types',
			'select_post_types',
			'replace_guids',
			'exclude_spam',
			'keep_active_plugins',
			'exclude_transients',
			'create_backup',
			'backup_option',
			'select_backup',
			$media_vars
		);
	}

	/**
	 * Perform CLI migration.
	 *
	 * @since 1.1
	 *
	 * @param  mixed Profile key or array
	 * @return void
	 */
	protected function _perform_cli_migration( $profile ) {
		$result  = wpmdb_migrate( $profile );
		if ( true === $result ) {
			WP_CLI::success( __( 'Migration successful.', 'wp-migrate-db-pro-cli' ) );
		} else if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
	}
}

/**
 * Deprecated WP Migrate DB Pro command. Use migratedb instead.
 */
class WPMDBCLI_Deprecated extends WPMDBCLI {
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
		parent::migrate( $args, $assoc_args );
	}
}

WP_CLI::add_command( 'wpmdb', 'WPMDBCLI_Deprecated' ); // deprecated older command
WP_CLI::add_command( 'migratedb', 'WPMDBCLI' );
