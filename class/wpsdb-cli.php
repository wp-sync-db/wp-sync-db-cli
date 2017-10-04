<?php
class WPSDB_CLI extends WPSDB_Addon {

	function __construct( $plugin_file_path ) {
		parent::__construct( $plugin_file_path );

		$this->plugin_slug = 'wp-sync-db-cli';
		$this->plugin_version = $GLOBALS['wpsdb_meta']['wp-sync-db-cli']['version'];

		if( ! $this->meets_version_requirements( '1.4b1' ) ) return;
	}

	function log_list( $list ) {
		foreach ( $list as $item ) {
			WP_CLI::log( "- $item" );
		}
	}

	function kebab_case_to_snake_case( $value ) {
		return preg_replace_callback('/-(.)/u', function($el) {
			return '_' . $el[1];
		}, $value);
	}

	function values_as_booleans_to_one_and_zero_strings( $array ) {
		$result = [];

		foreach ($array as $key => $value) {
			if ( $value === 'true' || $value === 'false' ) {
				$result[$key] = (string)(int)($value === 'true');
				continue;
			}

			$result[$key] = $value;
		}

		return $result;
	}

	function keys_from_kebab_case_to_snake_case( $array ) {
		$result = [];

		foreach ($array as $key => $value) {
				$result[ $this->kebab_case_to_snake_case( $key ) ] = $value;
		}

		return $result;
	}

	function cli_create_profile( $name, $assoc_args ) {
		$wpsdb_settings = get_option( 'wpsdb_settings' );

		$profile_exists = false;

		foreach ( $wpsdb_settings['profiles'] as $profile ) {
			if ( $profile['name'] === $name ) {
				$profile_exists = true;
				break;
			}
		}

		if ( $profile_exists ) {
			return $this->cli_error( sprintf( __( 'Profile with the name %1$s already exists.', 'wp-sync-db-cli' ), $name ) );
		}

		WP_CLI::log(  sprintf( __( 'Creating database migration profile with name %1$s.', 'wp-sync-db-cli' ), $name ) );

		$new_profile = array();

		$new_profile["name"] = $name;
		$new_profile["create_new_profile"] = $name;

		$new_profile["action"] = "pull";

		$new_profile['connection_info'] = implode( "\n", array( $assoc_args['remote-wordpress'], $assoc_args['token'] ) );
		unset( $assoc_args['remote-wordpress'] );
		unset( $assoc_args['token'] );

		$new_profile["table_migrate_option"] = 'migrate_only_with_prefix';

		// This replacement is typically done by calling the other WordPress site all
		// retrieving its variables.
		//
		// For the moment this is a TODO
		$new_profile["replace_old"] = array();
		$new_profile["replace_new"] = array();

		$new_profile["save_computer"] = "1";
		$new_profile["gzip_file"] = "1";
		$new_profile["replace_guids"] = "1";
		$new_profile["exclude_spam"] = "1";
		$new_profile["keep_active_plugins"] = "1";
		$new_profile["create_backup"] = "0";
		$new_profile["backup_option"] = "backup_selected";

		$new_profile["exclude_post_types"] = "0";
		$new_profile["exclude_transients"] = "1";
		$new_profile["media_files"] = "1";

		$new_profile["save_migration_profile"] = "1";
		$new_profile["save_migration_profile_option"] = "new";

		$values_for_wordpress = $this->keys_from_kebab_case_to_snake_case( $assoc_args );
		$values_for_wordpress = $this->values_as_booleans_to_one_and_zero_strings( $values_for_wordpress );
		$new_profile = array_merge( $new_profile, $values_for_wordpress );

		if ( isset( $assoc_args['exclude-post-types'] ) ) {
			$new_profile['exclude_post_types'] = '1';
			$new_profile['select_post_types'] = explode( ',', $assoc_args['exclude-post-types'] );

			WP_CLI::log(  __( 'Excluding WordPress post types from migration profile:',  'wp-sync-db-cli' ) );
			$this->log_list( $new_profile['select_post_types'] );
		}

		if ( isset( $assoc_args['migrate-tables'] ) ) {
			$new_profile["table_migrate_option"] = 'migrate_select';

			if ( $new_profile["migrate-tables"] === 'outlandish' ) {
				WP_CLI::log(  __( 'Selecting Outlandish default WordPress tables for migraton.',  'wp-sync-db-cli' ) );
				$new_profile["select_tables"] = array(
					"wp_commentmeta",
		      "wp_comments",
		      "wp_links",
		      "wp_options",
		      "wp_p2p",
		      "wp_p2pmeta",
		      "wp_postmeta",
		      "wp_posts",
		      "wp_term_relationships",
		       "wp_term_taxonomy",
		      "wp_termmeta",
		      "wp_terms",
		      "wp_usermeta",
		      "wp_users",
				);
			} else {
				$new_profile["select_tables"] = explode( ',', $assoc_args['migrate-tables'] );
				WP_CLI::log(  __( 'The following tables are selected for migration:',  'wp-sync-db-cli' ) );
				$this->log_list( $new_profile["select_tables"] );
			}
		}

		$wpsdb_settings['profiles'][] = $new_profile;
		update_option( 'wpsdb_settings', $wpsdb_settings );

		return true;
	}

	function cli_migration( $profile ) {
		global $wpsdb;
		$wpsdb_settings = get_option( 'wpsdb_settings' );
		--$profile;
		if( ! $this->meets_version_requirements( '1.4b1' ) ) return $this->cli_error( __( 'Please update WP Sync DB.', 'wp-sync-db-cli' ) );
		if( ! isset( $profile ) ) return $this->cli_error( __( 'Profile ID missing.', 'wp-sync-db-cli' ) );
		if( ! isset( $wpsdb_settings['profiles'][$profile] ) ) return $this->cli_error( __( 'Profile ID not found.', 'wp-sync-db-cli' ) );

		$this->set_time_limit();
		$wpsdb->set_cli_migration();

		$profile = apply_filters( 'wpsdb_cli_profile_before_migration', $wpsdb_settings['profiles'][$profile] );
		$connection_info = explode( "\n", $profile['connection_info'] );
		$form_data = http_build_query( $profile );

		if( 'savefile' == $profile['action'] ) return $this->cli_error( __( 'Exports not supported for CLI migrations. Please instead select push or pull instead.', 'wp-sync-db-cli' ) );

		do_action( 'wpsdb_cli_before_verify_connection_to_remote_site', $profile );

		// this request returns certain information from the remote machine, e.g. tables, prefix, bottleneck, gzip, etc
		$_POST['intent'] = $profile['action'];
		$_POST['url'] = trim( $connection_info[0] );
		$_POST['key'] = trim( $connection_info[1] );
		$_POST = apply_filters( 'wpsdb_cli_verify_connection_to_remote_site_args', $_POST, $profile );
		$response = $wpsdb->ajax_verify_connection_to_remote_site();
		if( is_wp_error( $verify_connection_response = $this->verify_cli_response( $response, 'ajax_verify_connection_to_remote_site()' ) ) ) return $verify_connection_response;

		$verify_connection_response = apply_filters( 'wpsdb_cli_verify_connection_response', $verify_connection_response );
		do_action( 'wpsdb_cli_before_initiate_migration', $profile, $verify_connection_response );

		// this request does one last verification check and creates export / backup files (if required)
		$_POST['form_data'] = $form_data;
		$_POST['stage'] = ( '0' == $profile['create_backup'] ) ? 'migrate' : 'backup';
		$_POST = apply_filters( 'wpsdb_cli_initiate_migration_args', $_POST, $profile, $verify_connection_response );
		$response = $wpsdb->ajax_initiate_migration();
		if( is_wp_error( $initiate_migration_response = $this->verify_cli_response( $response, 'ajax_initiate_migration()' ) ) ) return $initiate_migration_response;

		$initiate_migration_response = apply_filters( 'wpsdb_cli_initiate_migration_response', $initiate_migration_response );

		// determine which tables to backup (if required)
		$tables_to_backup = array();
		if( 'backup' == $_POST['stage'] ) {
			if( 'push' == $profile['action'] ) {
				if( 'backup_only_with_prefix' == $profile['backup_option'] ) {
					$tables_to_backup = $verify_connection_response['prefixed_tables'];
				} elseif( 'backup_selected' == $profile['backup_option'] ) {
					$tables_to_backup = array_intersect( $profile['select_backup'], $verify_connection_response['tables'] );
				} elseif( 'backup_manual_select' == $profile['backup_option'] ) {
					$tables_to_backup = array_intersect( $profile['select_backup'], $verify_connection_response['tables'] );
				}
			} else {
				if( 'backup_only_with_prefix' == $profile['backup_option'] ) {
					$tables_to_backup = $this->get_tables( 'prefix' );
				} elseif( 'backup_selected' == $profile['backup_option'] ) {
					$tables_to_backup = array_intersect( $profile['select_backup'], $this->get_tables() );
				} elseif( 'backup_manual_select' == $profile['backup_option'] ) {
					$tables_to_backup = array_intersect( $profile['select_backup'], $this->get_tables() );
				}
			}
		}
		$tables_to_backup = apply_filters( 'wpsdb_cli_tables_to_backup', $tables_to_backup, $profile, $verify_connection_response, $initiate_migration_response );

		// determine which tables to migrate
		$tables_to_migrate = array();
		if( 'push' == $profile['action'] ) {
			if( 'migrate_only_with_prefix' == $profile['table_migrate_option'] ) {
				$tables_to_migrate = $this->get_tables( 'prefix' );
			} elseif( 'migrate_select' == $profile['table_migrate_option'] ) {
				$tables_to_migrate = array_intersect( $profile['select_tables'], $this->get_tables() );
			}
		} elseif( 'pull' == $profile['action'] ) {
			if( 'migrate_only_with_prefix' == $profile['table_migrate_option'] ) {
				$tables_to_migrate = $verify_connection_response['prefixed_tables'];
			} elseif( 'migrate_select' == $profile['table_migrate_option'] ) {
				$tables_to_migrate = array_intersect( $profile['select_tables'], $verify_connection_response['tables'] );
			}
		}
		$tables_to_migrate = apply_filters( 'wpsdb_cli_tables_to_migrate', $tables_to_migrate, $profile, $verify_connection_response, $initiate_migration_response );

		$_POST['dump_filename'] = $initiate_migration_response['dump_filename'];
		$_POST['gzip'] = ( '1' == $verify_connection_response['gzip'] ) ? 1 : 0;
		$_POST['bottleneck'] = $verify_connection_response['bottleneck'];
		$_POST['prefix'] = $verify_connection_response['prefix'];

		$tables_to_process = ( 'backup' == $_POST['stage'] ) ? $tables_to_backup : $tables_to_migrate;
		$stage_interator = ( 'backup' == $_POST['stage'] ) ? 1 : 2;

		do_action( 'wpsdb_cli_before_migrate_tables', $profile, $verify_connection_response, $initiate_migration_response );

		do {
			foreach( $tables_to_process as $key => $table ) {
				$current_row = -1;
				$primary_keys = '';
				$_POST['table'] = $table;
				$_POST['last_table'] = ( $key == count( $tables_to_process ) - 1 ) ? '1' : '0';

				do {
					// reset the current chunk
					$wpsdb->empty_current_chunk();

					$_POST['current_row'] = $current_row;
					$_POST['primary_keys'] = $primary_keys;
					$_POST = apply_filters( 'wpsdb_cli_migrate_table_args', $_POST, $profile, $verify_connection_response, $initiate_migration_response );
					$response = $wpsdb->ajax_migrate_table();
					if( is_wp_error( $migrate_table_response = $this->verify_cli_response( $response, 'ajax_migrate_table()' ) ) ) return $migrate_table_response;
					$migrate_table_response = apply_filters( 'wpsdb_cli_migrate_table_response', $migrate_table_response, $_POST, $profile, $verify_connection_response, $initiate_migration_response );

					$current_row = $migrate_table_response['current_row'];
					$primary_keys = $migrate_table_response['primary_keys'];

				} while ( -1 != $current_row );
			}
			++$stage_interator;
			$_POST['stage'] = 'migrate';
			$tables_to_process = $tables_to_migrate;

		} while ( $stage_interator < 3 );

		do_action( 'wpsdb_cli_before_finalize_migration', $profile, $verify_connection_response, $initiate_migration_response );

		$finalize_migration_response = apply_filters( 'wpsdb_cli_finalize_migration', true, $profile, $verify_connection_response, $initiate_migration_response );
		if( is_wp_error( $finalize_migration_response ) ) return $finalize_migration_response;

		$_POST['tables'] = implode( ',', $tables_to_process );
		$_POST['temp_prefix'] = $verify_connection_response['temp_prefix'];
		$_POST = apply_filters( 'wpsdb_cli_finalize_migration_args', $_POST, $profile, $verify_connection_response, $initiate_migration_response );
		// don't send redundant POST variables
		$_POST = $this->filter_post_elements( $_POST, array( 'action', 'intent', 'url', 'key', 'form_data', 'prefix', 'type', 'location', 'tables', 'temp_prefix' ) );
		$response = trim( $wpsdb->ajax_finalize_migration() );
		if( ! empty( $response ) ) return $this->cli_error( $response );

		do_action( 'wpsdb_cli_after_finalize_migration', $profile, $verify_connection_response, $initiate_migration_response );

		return true;
	}

	function verify_cli_response( $response, $function_name ) {
		global $wpsdb;
		$response = trim( $response );
		if( false === $response ) {
			return $this->cli_error( $this->error );
		}
		if( false === $wpsdb->is_json( $response ) ) {
			return $this->cli_error( sprintf( __( '%1$s was expecting a JSON response, instead we received: %2$s', 'wp-sync-db-cli' ), $function_name, $response ) );
		}
		$response = json_decode( $response, true );
		if( isset( $response['wpsdb_error'] ) ) {
			return $this->cli_error( $response['body'] );
		}
		return $response;
	}

	function cli_error( $message ){
		return new WP_Error( 'wpsdb_cli_error', $message );
	}

}
