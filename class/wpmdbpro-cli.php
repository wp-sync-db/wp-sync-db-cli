<?php

class WPMDBPro_CLI extends WPMDBPro_Addon {

	/**
	 * Instance of WPMDBPro.
	 *
	 * @var WPMDBPro
	 */
	protected $wpmdbpro;

	/**
	 * Migration profile.
	 *
	 * @var array
	 */
	protected $profile;

	/**
	 * Data to post during migration.
	 *
	 * @var array
	 */
	protected $post_data = array();

	/* remote connection info */
	protected $remote;
	protected $migrate;

	function __construct( $plugin_file_path ) {
		parent::__construct( $plugin_file_path );

		$this->plugin_slug = 'wp-migrate-db-pro-cli';
		$this->plugin_version = $GLOBALS['wpmdb_meta']['wp-migrate-db-pro-cli']['version'];

		if ( ! $this->meets_version_requirements( '1.4.5' ) ) {
			return;
		}

		global $wpmdbpro;
		$this->wpmdbpro = $wpmdbpro;
	}

	/**
	 * Get profile by key.
	 *
	 * @since 1.1
	 *
	 * @param  string|int     Profile key
	 * @return array|WP_Error If profile exists return array, otherwise WP_Error.
	 */
	public function get_profile_by_key( $key ) {
		$wpmdb_settings = get_site_option( 'wpmdb_settings' );
		--$key;

		if ( ! isset( $wpmdb_settings['profiles'][ $key ] ) ) {
			return $this->cli_error( __( 'Profile ID not found.', 'wp-migrate-db-pro-cli' ) );
		}

		return $wpmdb_settings['profiles'][ $key ];
	}

	/**
	 * Performs check before CLI migration given a profile data.
	 *
	 * @param  mixed Profile key or array.
	 * @return mixed Returns true is succeed or WP_Error if failed.
	 */
	public function pre_cli_migration_check( $profile ) {
		if ( ! $this->meets_version_requirements( '1.4.5' ) ) {
			return $this->cli_error( __( 'Please update WP Migrate DB Pro.', 'wp-migrate-db-pro-cli' ) );
		}

		if ( ! isset( $profile ) ) {
			return $this->cli_error( __( 'Profile ID missing.', 'wp-migrate-db-pro-cli' ) );
		}

		if ( is_array( $profile ) ) {
			$query_str = http_build_query( $profile );
			$profile   = $this->wpmdbpro->parse_migration_form_data( $query_str );
			$profile   = wp_parse_args(
				$profile,
				array(
					'save_computer'             => '0',
					'gzip_file'                 => '0',
					'replace_guids'             => '0',
					'exclude_spam'              => '0',
					'keep_active_plugins'       => '0',
					'create_backup'             => '0',
					'exclude_post_types'        => '0',
					'compatibility_older_mysql' => '0',
				)
			);
		} else {
			$profile = $this->get_profile_by_key( absint( $profile ) );
		}

		if ( is_wp_error( $profile ) ) {
			return $profile;
		}

		$this->profile = apply_filters( 'wpmdb_cli_profile_before_migration', $profile );

		if ( 'savefile' === $this->profile['action'] ) {
			return $this->cli_error( __( 'Exports not supported for CLI migrations. Please select push or pull instead.', 'wp-migrate-db-pro-cli' ) );
		}

		return true;
	}

	/**
	 * Performs CLI migration given a profile data.
	 *
	 * @param  mixed Profile key or array.
	 * @return mixed Returns true is succeed or WP_Error if failed.
	 */
	public function cli_migration( $profile ) {
		$pre_check = $this->pre_cli_migration_check( $profile );
		if ( is_wp_error( $pre_check ) ) {
			return $pre_check;
		}

		$this->set_time_limit();
		$this->wpmdbpro->set_cli_migration();

		$this->remote = $this->verify_remote_connection();
		if ( is_wp_error( $this->remote ) ) {
			return $this->remote;
		}

		// Default the find/replace pairs if nothing specified so that we don't break the target.
		if ( empty( $this->profile['replace_old'] ) && empty( $this->profile['replace_new'] ) ) {
			$local = array(
				'',
				preg_replace( '#^https?:#', '', home_url() ),
				$this->get_absolute_root_file_path()
			);
			$remote = array(
				'',
				preg_replace( '#^https?:#', '', $this->remote['url'] ),
				$this->remote['path']
			);

			if ( 'push' == $this->profile['action'] ) {
				$this->profile['replace_old'] = $local;
				$this->profile['replace_new'] = $remote;
			} else {
				$this->profile['replace_old'] = $remote;
				$this->profile['replace_new'] = $local;
			}
			unset( $local, $remote );
		}

		$this->migration = $this->cli_initiate_migration();
		if ( is_wp_error( $this->migration ) ) {
			return $this->migration;
		}

		$this->post_data['dump_filename']      = $this->migration['dump_filename'];
		$this->post_data['gzip']               = ( '1' == $this->remote['gzip'] ) ? 1 : 0;
		$this->post_data['bottleneck']         = $this->remote['bottleneck'];
		$this->post_data['prefix']             = $this->remote['prefix'];
		$this->post_data['db_version']         = $this->migration['db_version'];
		$this->post_data['site_url']           = $this->migration['site_url'];
		$this->post_data['find_replace_pairs'] = $this->migration['find_replace_pairs'];

		$tables_to_process = $this->migrate_tables();
		if ( is_wp_error( $tables_to_process ) ) {
			return $tables_to_process;
		}

		$this->post_data['tables'] = implode( ',', $tables_to_process );
		$this->post_data['temp_prefix'] = $this->remote['temp_prefix'];

		$finalize = $this->finalize_migration();
		if ( is_wp_error( $finalize ) ) {
			return $finalize;
		}

		return true;
	}

	function verify_cli_response( $response, $function_name ) {
		$response = trim( $response );
		if ( false === $response ) {
			return $this->cli_error( $this->error );
		}

		if ( false === $this->wpmdbpro->is_json( $response ) ) {
			return $this->cli_error( sprintf( __( 'We were expecting a JSON response, instead we received: %2$s (function name: %1$s)', 'wp-migrate-db-pro-cli' ), $function_name, $response ) );
		}

		$response = json_decode( $response, true );
		if ( isset( $response['wpmdb_error'] ) ) {
			return $this->cli_error( $response['body'] );
		}

		// display warnings and non fatal error messages as CLI warnings without aborting
		if ( isset( $response['wpmdb_warning'] ) || isset( $response['wpmdb_non_fatal_error'] ) ) {
			$body = ( isset ( $response['cli_body'] ) ) ? $response['cli_body'] : $response['body'];
			$messages = maybe_unserialize( $body );
			foreach ( ( array ) $messages as $message ) {
				if ( $message ) {
					WP_CLI::warning( $message );
				}
			}
		}

		return $response;
	}

	function cli_error( $message ){
		return new WP_Error( 'wpmdb_cli_error', $message );
	}

	/**
	 * Retrieve information from the remote machine, e.g. tables, prefix, bottleneck, gzip, etc
	 */
	function verify_remote_connection() {
		do_action( 'wpmdb_cli_before_verify_connection_to_remote_site', $this->profile );

		WP_CLI::log( __( 'Verifying connection...', 'wp-migrate-db-pro-cli' ) );

		$connection_info = preg_split( '/\s+/', $this->profile['connection_info'] );

		$remote_site_args           = $this->post_data;
		$remote_site_args['intent'] = $this->profile['action'];
		$remote_site_args['url']    = trim( $connection_info[0] );
		$remote_site_args['key']    = trim( $connection_info[1] );
		$this->post_data = apply_filters( 'wpmdb_cli_verify_connection_to_remote_site_args', $remote_site_args, $this->profile );

		// $response = $this->wpmdbpro->verify_connection_to_remote_site( $this->post_data );
		$response = $this->verify_connection_to_remote_site( $this->post_data );

		$verified_response = $this->verify_cli_response( $response, 'ajax_verify_connection_to_remote_site()' );
		if ( ! is_wp_error( $verified_response ) ) {
			$verified_response = apply_filters( 'wpmdb_cli_verify_connection_response', $verified_response );
		}

		return $verified_response;
	}

	/**
	 * Perform one last verification check and creates export / backup files (if required)
	 *
	 * @return array|WP_Error
	 */
	function cli_initiate_migration() {
		do_action( 'wpmdb_cli_before_initiate_migration', $this->profile, $this->remote );

		WP_CLI::log( __( 'Initiating migration...', 'wp-migrate-db-pro-cli' ) );

		$migration_args = $this->post_data;
		$migration_args['form_data'] = http_build_query( $this->profile );
		$migration_args['stage'] = ( '0' == $this->profile['create_backup'] ) ? 'migrate' : 'backup';
		$this->post_data = apply_filters( 'wpmdb_cli_initiate_migration_args', $migration_args, $this->profile, $this->remote );

		$response = $this->initiate_migration( $this->post_data );

		$initiate_migration_response = $this->verify_cli_response( $response, 'ajax_initiate_migration()' );
		if ( ! is_wp_error( $initiate_migration_response ) ) {
			$initiate_migration_response = apply_filters( 'wpmdb_cli_initiate_migration_response', $initiate_migration_response );
		}

		return $initiate_migration_response;
	}

	/**
	 * Determine which tables to backup (if required)
	 *
	 * @return mixed|void
	 */
	function get_tables_to_backup() {
		$tables_to_backup = array();
		$action           = $this->profile['action'];

		if ( ! in_array( $action, array( 'push', 'pull' ) ) ) {
			$action = 'pull';
		}

		if ( 'push' === $action ) {
			$all_tables      = $this->remote['tables'];
			$prefixed_tables = $this->remote['prefixed_tables'];
		} else {
			$all_tables      = $this->get_tables();
			$prefixed_tables = $this->get_tables( 'prefix' );
		}


		switch ( $this->profile['backup_option'] ) {
			case 'backup_only_with_prefix':
				$tables_to_backup = $prefixed_tables;
				break;
			case 'backup_selected':
				//
				// When tables to migrate is tables with prefix, select_tables
				// might be empty. Intersecting it with remote/local tables
				// throws notice/warning and won't backup the file either.
				//
				if ( 'migrate_only_with_prefix' ===  $this->profile['table_migrate_option'] ) {
					$tables_to_backup = $prefixed_tables;
				} else {
					$tables_to_backup = array_intersect( $this->profile['select_tables'], $all_tables );
				}
				break;
			case 'backup_manual_select':
				$tables_to_backup = array_intersect( $this->profile['select_backup'], $all_tables );
				break;
		}

		return apply_filters( 'wpmdb_cli_tables_to_backup', $tables_to_backup, $this->profile, $this->remote, $this->migrate );
	}

	/**
	 * Determine which tables to migrate
	 */
	function get_tables_to_migrate() {
		$tables_to_migrate = array();
		if ( 'push' == $this->profile['action'] ) {
			if ( 'migrate_only_with_prefix' == $this->profile['table_migrate_option'] ) {
				$tables_to_migrate = $this->get_tables( 'prefix' );
			} elseif ( 'migrate_select' == $this->profile['table_migrate_option'] ) {
				$tables_to_migrate = array_intersect( $this->profile['select_tables'], $this->get_tables() );
			}
		} elseif ( 'pull' == $this->profile['action'] ) {
			if ( 'migrate_only_with_prefix' == $this->profile['table_migrate_option'] ) {
				$tables_to_migrate = $this->remote['prefixed_tables'];
			} elseif ( 'migrate_select' == $this->profile['table_migrate_option'] ) {
				$tables_to_migrate = array_intersect( $this->profile['select_tables'], $this->remote['tables'] );
			}
		}

		return apply_filters( 'wpmdb_cli_tables_to_migrate', $tables_to_migrate, $this->profile, $this->remote, $this->migration );
	}

	function get_progress_bar( $tables, $stage ) {
		if ( 1 === $stage ) { // 1 = backup stage, 2 = migration stage
			$progress_label = __( 'Performing backup', 'wp-migrate-db-pro-cli' );
		} else {
			$progress_label = __( 'Migrating tables', 'wp-migrate-db-pro-cli' );
		}

		$progress_label = str_pad( $progress_label, 20, ' ' );

		$count = $this->get_total_rows_from_table_list( $tables, $stage );

		return new \cli\progress\Bar( $progress_label, $count );
	}

	function get_total_rows_from_table_list( $tables, $stage ) {
		static $cached_results = array();

		if ( isset( $cached_results[ $stage ] ) ) {
			return $cached_results[ $stage ];
		}

		$table_rows               = $this->get_row_counts_from_table_list( $tables, $stage );
		$cached_results[ $stage ] = array_sum( array_intersect_key( $table_rows, array_flip( $tables ) ) );

		return $cached_results[ $stage ];
	}

	function get_row_counts_from_table_list( $tables, $stage ) {
		static $cached_results = array();

		if ( isset( $cached_results[ $stage ] ) ) {
			return $cached_results[ $stage ];
		}

		$migration_type    = $this->profile['action'];
		$local_table_rows  = $this->wpmdbpro->get_table_row_count();
		$remote_table_rows = $this->remote['table_rows'];

		if ( 1 === $stage ) { // 1 = backup stage, 2 = migration stage
			$cached_results[ $stage ] = ( 'pull' === $migration_type ) ? $local_table_rows : $remote_table_rows;
		} else {
			$cached_results[ $stage ] = ( 'pull' === $migration_type ) ? $remote_table_rows : $local_table_rows;
		}

		return $cached_results[ $stage ];
	}

	/**
	 * @return array|mixed|string|void|WP_Error
	 */
	function migrate_tables() {
		$tables_to_backup  = $this->get_tables_to_backup();
		$tables_to_migrate = $this->get_tables_to_migrate();

		if ( 'backup' == $this->post_data['stage'] &&
		     'backup_manual_select' == $this->profile['backup_option'] &&
		     array_diff( $this->profile['select_backup'], $tables_to_backup )
		) {
			return $this->cli_error( __( 'Invalid backup option or non-existent table selected for backup.', 'wp-migrate-db-pro-cli' ) );
		}

		$tables         = ( 'backup' == $this->post_data['stage'] ) ? $tables_to_backup : $tables_to_migrate;
		$stage_iterator = ( 'backup' == $this->post_data['stage'] ) ? 1 : 2;
		$table_rows     = $this->get_row_counts_from_table_list( $tables, $stage_iterator );

		do_action( 'wpmdb_cli_before_migrate_tables', $this->profile, $this->remote, $this->migration );

		$notify = $this->get_progress_bar( $tables, $stage_iterator );
		$args   = $this->post_data;

		do {
			$migration_progress = 0;

			foreach ( $tables as $key => $table ) {
				$current_row         = -1;
				$primary_keys        = '';
				$table_progress      = 0;
				$table_progress_last = 0;

				$args['table']      = $table;
				$args['last_table'] = ( $key == count( $tables ) - 1 ) ? '1' : '0';

				do {
					// reset the current chunk
					$this->wpmdbpro->empty_current_chunk();

					$args['current_row']  = $current_row;
					$args['primary_keys'] = $primary_keys;
					$args = apply_filters( 'wpmdb_cli_migrate_table_args', $args, $this->profile, $this->remote, $this->migration );

					$response = $this->migrate_table( $args );

					$migrate_table_response = $this->verify_cli_response( $response, 'ajax_migrate_table()' );

					if ( is_wp_error( $migrate_table_response ) ) {
						return $migrate_table_response;
					}

					$migrate_table_response = apply_filters( 'wpmdb_cli_migrate_table_response', $migrate_table_response, $_POST, $this->profile, $this->remote, $this->migration );

					$current_row  = $migrate_table_response['current_row'];
					$primary_keys = $migrate_table_response['primary_keys'];

					$last_migration_progress = $migration_progress;

					if ( -1 == $current_row ) {
						$migration_progress -= $table_progress;
						$migration_progress += $table_rows[ $table ];
					} else {
						if ( 0 === $table_progress_last ) {
							$table_progress_last  = $current_row;
							$table_progress       = $table_progress_last;
							$migration_progress  += $table_progress_last;
						} else {
							$iteration_progress   = $current_row - $table_progress_last;
							$table_progress_last  = $current_row;
							$table_progress      += $iteration_progress;
							$migration_progress  += $iteration_progress;
						}
					}

					$increment = $migration_progress - $last_migration_progress;

					$notify->tick( $increment );

				} while ( -1 != $current_row );
			}

			$notify->finish();

			++$stage_iterator;
			$args['stage'] = 'migrate';
			$tables        = $tables_to_migrate;
			$table_rows    = $this->get_row_counts_from_table_list( $tables, $stage_iterator );

			if ( $stage_iterator < 3 ) {
				$notify = $this->get_progress_bar( $tables, $stage_iterator );
			}
		} while ( $stage_iterator < 3 );

		$this->post_data = $args;

		return $tables;
	}

	function finalize_migration() {
		do_action( 'wpmdb_cli_before_finalize_migration', $this->profile, $this->remote, $this->migration );

		WP_CLI::log( __( 'Cleaning up...', 'wp-migrate-db-pro-cli' ) );

		$finalize = apply_filters( 'wpmdb_cli_finalize_migration', true, $this->profile, $this->remote, $this->migration );
		if ( is_wp_error( $finalize ) ) {
			return $finalize;
		}

		$this->post_data = apply_filters( 'wpmdb_cli_finalize_migration_args', $this->post_data, $this->profile, $this->remote, $this->migration );

		// don't send redundant POST variables
		$args = $this->filter_post_elements( $this->post_data, array( 'action', 'intent', 'url', 'key', 'form_data', 'prefix', 'type', 'location', 'tables', 'temp_prefix' ) );

		$response = trim( $this->finalize( $args ) );
		if ( ! empty( $response ) ) {
			return $this->cli_error( $response );
		}

		do_action( 'wpmdb_cli_after_finalize_migration', $this->profile, $this->remote, $this->migration );
	}

	// stub for ajax_verify_connection_to_remote_site()
	function verify_connection_to_remote_site( $args = false ) {
		$_POST = $args;
		$response = $this->wpmdbpro->ajax_verify_connection_to_remote_site();

		return $response;
	}

	// stub for ajax_initiate_migration()
	function initiate_migration( $args = false ) {
		$_POST = $args;
		$response = $this->wpmdbpro->ajax_initiate_migration();

		return $response;
	}

	// stub for ajax_migrate_table()
	function migrate_table( $args = false ) {
		$_POST = $args;
		$response = $this->wpmdbpro->ajax_migrate_table();

		return $response;
	}

	// stub for ajax_finalize_migration()
	function finalize( $args = false ) {
		$_POST = $args;
		$response = $this->wpmdbpro->ajax_finalize_migration();

		return $response;
	}
}
