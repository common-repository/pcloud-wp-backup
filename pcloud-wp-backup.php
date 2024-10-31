<?php
/**
 * Pcloud WP Backup plugin
 *
 * @package pcloud_wp_backup
 * @author pCloud
 *
 * Plugin Name: pCloud WP Backup
 * Plugin URI: https://www.pcloud.com
 * Summary: pCloud WP Backup plugin
 * Description: pCloud WP Backup has been created to make instant backups of your blog and its data, regularly.
 * Version: 2.0.1
 * Requires PHP: 8.0
 * Author: pCloud
 * URI: https://www.pcloud.com
 * License: Copyright 2013-2023 - pCloud
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License, version 2, as published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

use Pcloud\Classes\wp2pclouddbbackup;
use Pcloud\Classes\wp2pclouddebugger;
use Pcloud\Classes\wp2pcloudfilebackup;
use Pcloud\Classes\wp2pcloudfilerestore;
use Pcloud\Classes\wp2pcloudfuncs;
use Pcloud\Classes\wp2pcloudlogger;

require plugin_dir_path( __FILE__ ) . 'Pcloud/class-autoloader.php';

if ( ! defined( 'PCLOUD_API_LOCATIONID' ) ) {
	define( 'PCLOUD_API_LOCATIONID', 'wp2pcl_api_locationid' );
}
if ( ! defined( 'PCLOUD_AUTH_KEY' ) ) {
	define( 'PCLOUD_AUTH_KEY', 'wp2pcl_auth' );
}
if ( ! defined( 'PCLOUD_AUTH_MAIL' ) ) {
	define( 'PCLOUD_AUTH_MAIL', 'wp2pcl_auth_mail' );
}
if ( ! defined( 'PCLOUD_SCHDATA_KEY' ) ) {
	define( 'PCLOUD_SCHDATA_KEY', 'wp2pcl_schdata' );
}
if ( ! defined( 'PCLOUD_SCHHOUR_FROM_KEY' ) ) {
	define( 'PCLOUD_SCHHOUR_FROM_KEY', 'wp2pcl_schhour_from' );
}
if ( ! defined( 'PCLOUD_SCHHOUR_TO_KEY' ) ) {
	define( 'PCLOUD_SCHHOUR_TO_KEY', 'wp2pcl_schhour_to' );
}
if ( ! defined( 'PCLOUD_SCHDATA_INCLUDE_MYSQL' ) ) {
	define( 'PCLOUD_SCHDATA_INCLUDE_MYSQL', 'wp2pcl_include_mysql' );
}
if ( ! defined( 'PCLOUD_OPERATION' ) ) {
	define( 'PCLOUD_OPERATION', 'wp2pcl_operation' );
}
if ( ! defined( 'PCLOUD_HAS_ACTIVITY' ) ) {
	define( 'PCLOUD_HAS_ACTIVITY', 'wp2pcl_has_activity' );
}
if ( ! defined( 'PCLOUD_LOG' ) ) {
	define( 'PCLOUD_LOG', 'wp2pcl_logs' );
}
if ( ! defined( 'PCLOUD_DBG_LOG' ) ) {
	define( 'PCLOUD_DBG_LOG', 'wp2pcl_dbg_logs' );
}
if ( ! defined( 'PCLOUD_NOTIFICATIONS' ) ) {
	define( 'PCLOUD_NOTIFICATIONS', 'wp2pcl_notifications' );
}
if ( ! defined( 'PCLOUD_LAST_BACKUPDT' ) ) {
	define( 'PCLOUD_LAST_BACKUPDT', 'wp2pcl_last_backupdt' );
}
if ( ! defined( 'PCLOUD_QUOTA' ) ) {
	define( 'PCLOUD_QUOTA', 'wp2pcl_quota' );
}
if ( ! defined( 'PCLOUD_USEDQUOTA' ) ) {
	define( 'PCLOUD_USEDQUOTA', 'wp2pcl_usedquota' );
}
if ( ! defined( 'PCLOUD_MAX_NUM_FAILURES_NAME' ) ) {
	define( 'PCLOUD_MAX_NUM_FAILURES_NAME', 'wp2pcl_max_num_failures' );
}
if ( ! defined( 'PCLOUD_ASYNC_UPDATE_VAL' ) ) {
	define( 'PCLOUD_ASYNC_UPDATE_VAL', 'wp2pcl_async_upd_item' );
}
if ( ! defined( 'PCLOUD_BACKUP_FILE_INDEX' ) ) {
	define( 'PCLOUD_BACKUP_FILE_INDEX', 'wp2pcl_backup_file_index' );
}
if ( ! defined( 'PCLOUD_OAUTH_CLIENT_ID' ) ) {
	define( 'PCLOUD_OAUTH_CLIENT_ID', 'beFbFDM0paj' );
}
if ( ! defined( 'PCLOUD_TEMP_DIR' ) ) {
	$backup_dir = rtrim( WP_CONTENT_DIR, '/' ) . '/pcloud_tmp';
	define( 'PCLOUD_TEMP_DIR', $backup_dir );
}
if ( ! defined( 'PCLOUD_DEBUG' ) ) {
	define( 'PCLOUD_DEBUG', false );
}
if ( ! defined( 'PCLOUD_PLUGIN_MIN_PHP_VERSION' ) ) {
	define( 'PCLOUD_PLUGIN_MIN_PHP_VERSION', '8.0' );
}

// The maximum number of failures allowed.
$max_num_failures = 1800;

/**
 * This hack will increase the wp_remote_request timeout, which otherwise dies after 5-10sec.
 *
 * @return int
 * @noinspection PhpUnused
 */
function pcl_wb_bkup_timeout_extend(): int {
	return 180;
}

add_filter( 'http_request_timeout', 'pcl_wb_bkup_timeout_extend' );

$sitename = preg_replace( '/http(s?):\/\//', '', get_bloginfo( 'url' ) );
$sitename = str_replace( '.', '_', $sitename );

define( 'PCLOUD_BACKUP_DIR', 'WORDPRESS_BACKUPS/' . strtoupper( $sitename ) );

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$plugin_path_base = __DIR__;

$num_failures = wp2pcloudfuncs::get_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME );
if ( empty( $num_failures ) ) {
	wp2pcloudfuncs::set_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME, $max_num_failures );
}

$backup_file_index = wp2pcloudfuncs::get_storred_val( PCLOUD_BACKUP_FILE_INDEX );
if ( empty( $backup_file_index ) ) {
	$backup_file_index = time();
	wp2pcloudfuncs::set_storred_val( PCLOUD_BACKUP_FILE_INDEX, $backup_file_index );
}

/**
 * This function creates a menu item
 *
 * @return void
 * @noinspection PhpUnused
 */
function backup_to_pcloud_admin_menu(): void {
	$img_url = rtrim( plugins_url( '/assets/img/logo_16.png', __FILE__ ) );
	add_menu_page( 'WP2pCloud', 'pCloud Backup', 'administrator', 'wp2pcloud_settings', 'wp2pcloud_display_settings', $img_url );
}

/**
 * This function handles all ajax request sent back to the plugin
 *
 * @throws Exception Standart exception will be thrown.
 * @noinspection PhpUnused
 */
function wp2pcl_ajax_process_request(): void {

	global $sitename;

	$result = array(
		'status'  => 1, // 0: OK, 1+: error
		'message' => '',
	);

	$m = isset( $_GET['method'] ) ? sanitize_text_field( wp_unslash( $_GET['method'] ) ) : false;

	$dbg_mode = false;
	if ( isset( $_GET['dbg'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['dbg'] ) ) ) {
		$dbg_mode = true;
	}

	if ( 'unlink_acc' === $m ) {

		wp2pcloudfuncs::set_storred_val( PCLOUD_AUTH_KEY, '' );
		wp2pcloudfuncs::set_storred_val( PCLOUD_AUTH_MAIL, '' );
		wp2pcloudfuncs::set_storred_val( PCLOUD_QUOTA, '1' );
		wp2pcloudfuncs::set_storred_val( PCLOUD_USEDQUOTA, '1' );
		wp2pcloudfuncs::set_storred_val( PCLOUD_API_LOCATIONID, '1' );
		wp2pcloudfuncs::set_storred_val( PCLOUD_SCHDATA_INCLUDE_MYSQL, '1' );

		$result['status'] = 0;

	} elseif ( 'set_with_mysql' === $m ) {

		if ( ! isset( $_POST['wp2pcl_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wp2pcl_nonce'] ) ) ) {
			$result['status']   = 15;
			$result['msg']      = '<p>Failed to validate the request!</p>';
			$result['sitename'] = $sitename;

			echo wp_json_encode( $result );

			return;
		}

		$withmysql = isset( $_POST['wp2pcl_withmysql'] ) ? '1' : '0';

		wp2pcloudfuncs::set_storred_val( PCLOUD_SCHDATA_INCLUDE_MYSQL, $withmysql );

		$result['status'] = 0;

	} elseif ( 'userinfo' === $m ) {

		if ( ! isset( $_GET['wp2pcl_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['wp2pcl_nonce'] ) ) ) {
			$result['status']   = 15;
			$result['msg']      = '<p>Failed to validate the request!</p>';
			$result['sitename'] = $sitename;

			echo wp_json_encode( $result );

			return;
		}

		$result['status'] = 0;

		$authkey  = wp2pcloudfuncs::get_storred_val( PCLOUD_AUTH_KEY );
		$apiep    = rtrim( 'https://' . wp2pcloudfuncs::get_api_ep_hostname() );
		$url      = $apiep . '/userinfo?access_token=' . $authkey;
		$response = wp_remote_get( $url );
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$response_body_list = json_decode( $response['body'] );
			if ( property_exists( $response_body_list, 'result' ) ) {
				$resp_result = intval( $response_body_list->result );
				if ( 0 === $resp_result ) {
					$result['data'] = $response_body_list;
				}
			}
		}
	} elseif ( 'listfolder' === $m ) {

		if ( ! isset( $_GET['wp2pcl_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['wp2pcl_nonce'] ) ) ) {
			$result['status']   = 15;
			$result['msg']      = '<p>Failed to validate the request!</p>';
			$result['sitename'] = $sitename;

			echo wp_json_encode( $result );

			return;
		}

		$result['status']   = 0;
		$result['contents'] = array();

		$authkey  = wp2pcloudfuncs::get_storred_val( PCLOUD_AUTH_KEY );
		$apiep    = rtrim( 'https://' . wp2pcloudfuncs::get_api_ep_hostname() );
		$url      = $apiep . '/listfolder?path=/' . PCLOUD_BACKUP_DIR . '&access_token=' . $authkey;
		$response = wp_remote_get( $url );
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$response_body_list = json_decode( $response['body'] );
			if ( property_exists( $response_body_list, 'result' ) ) {
				$resp_result = intval( $response_body_list->result );
				if ( ( 0 === $resp_result ) && property_exists( $response_body_list, 'metadata' ) && property_exists( $response_body_list->metadata, 'contents' ) ) {
					$result['folderid'] = $response_body_list->metadata->folderid;
					$result['contents'] = $response_body_list->metadata->contents;
				} else {
					pcl_verify_directory_structure();
				}
			}
		} else {
			$result['status'] = 65;
			$result['msg']    = '<p>Failed to get backup files list!</p>';
		}
	} elseif ( 'set_schedule' === $m ) {

		if ( ! isset( $_POST['wp2pcl_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wp2pcl_nonce'] ) ) ) {
			$result['status']   = 15;
			$result['msg']      = '<p>Failed to validate the request!</p>';
			$result['sitename'] = $sitename;

			echo wp_json_encode( $result );

			return;
		}

		$freq      = isset( $_POST['freq'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['freq'] ) ) ) : 't';
		$hour_from = isset( $_POST['hour_from'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['hour_from'] ) ) ) : '-1';
		$hour_to   = isset( $_POST['hour_to'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['hour_to'] ) ) ) : '-1';

		if ( 't' === $freq ) {

			wp2pclouddebugger::log( 'Test initiated !' );

			$freq = 'daily';

			wp2pcloudfuncs::set_storred_val( PCLOUD_LAST_BACKUPDT, '0' );

			wp_clear_scheduled_hook( 'init_autobackup' );

			wp2pcl_run_pcloud_backup_hook();
		}

		wp2pcloudfuncs::set_storred_val( PCLOUD_SCHDATA_KEY, $freq );
		wp2pcloudfuncs::set_storred_val( PCLOUD_SCHHOUR_FROM_KEY, $hour_from );
		wp2pcloudfuncs::set_storred_val( PCLOUD_SCHHOUR_TO_KEY, $hour_to );

		$result['status'] = 0;

	} elseif ( 'restore_archive' === $m ) {

		wp2pclouddebugger::generate_new( 'restore_archive at: ' . gmdate( 'Y-m-d H:i:s' ) );

		$memlimit    = ( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '---' );
		$memlimitini = ini_get( 'memory_limit' );
		wp2pclouddebugger::log( 'Memory limits: ' . $memlimit . ' / ' . $memlimitini );

		if ( ! isset( $_POST['wp2pcl_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wp2pcl_nonce'] ) ) ) {

			$result['status']   = 15;
			$result['msg']      = '<p>Failed to validate the request!</p>';
			$result['sitename'] = $sitename;

			echo wp_json_encode( $result );

			return;
		}

		wp2pcloudfuncs::set_execution_limits();

		wp2pcloudfuncs::set_storred_val( PCLOUD_HAS_ACTIVITY, '1' );

		wp2pcloudlogger::generate_new( "<span class='pcl_transl' data-i10nk='start_restore_at'>Start restore at</span> " . gmdate( 'Y-m-d H:i:s' ) );
		wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='prep_dwl_file_wait'>Preparing Download file request, please wait...</span>" );

		$file_id   = isset( $_POST['file_id'] ) ? intval( sanitize_text_field( wp_unslash( $_POST['file_id'] ) ) ) : 0;
		$folder_id = isset( $_POST['folder_id'] ) ? intval( sanitize_text_field( wp_unslash( $_POST['folder_id'] ) ) ) : 0;

		$doc_root_arr = explode( DIRECTORY_SEPARATOR, dirname( __FILE__ ) );
		array_pop( $doc_root_arr );
		array_pop( $doc_root_arr );
		array_pop( $doc_root_arr );

		$authkey  = wp2pcloudfuncs::get_storred_val( PCLOUD_AUTH_KEY );
		$hostname = wp2pcloudfuncs::get_api_ep_hostname();

		if ( $file_id > 0 || $folder_id > 0 || empty( $hostname ) ) {

			$apiep      = rtrim( 'https://' . wp2pcloudfuncs::get_api_ep_hostname() );
			$archives   = array();
			$total_size = 0;

			if ( $folder_id > 0 ) {

				$url      = $apiep . '/listfolder?folderid=' . $folder_id . '&access_token=' . $authkey;
				$response = wp_remote_get( $url );
				if ( is_array( $response ) && ! is_wp_error( $response ) ) {
					$response_body_list = json_decode( $response['body'] );
					if ( property_exists( $response_body_list, 'result' ) ) {
						$resp_result = intval( $response_body_list->result );
						if ( 0 === $resp_result && property_exists( $response_body_list, 'metadata' ) && property_exists( $response_body_list->metadata, 'contents' ) ) {
							foreach ( $response_body_list->metadata->contents as $item ) {
								if ( property_exists( $item, 'name' ) && property_exists( $item, 'fileid' ) ) {
									if ( 'backup.sql.zip' === $item->name || preg_match( '/^\d{3}_archive\.zip$/', $item->name ) ) {

										$url = $apiep . '/getfilelink?fileid=' . $item->fileid . '&access_token=' . $authkey;

										$response = wp_remote_get( $url );
										if ( is_array( $response ) && ! is_wp_error( $response ) ) {
											$r = json_decode( $response['body'] );
											if ( intval( $r->result ) === 0 ) {
												$url         = 'https://' . reset( $r->hosts ) . $r->path;
												$archives[]  = array(
													'fileid' => $item->fileid,
													'name' => $item->name,
													'size' => $item->size,
													'dwlurl' => $url,
												);
												$total_size += $item->size;
											}
										}
									}
								}
							}
						}
					}
				}
			} else {
				$url      = $apiep . '/getfilelink?fileid=' . $file_id . '&access_token=' . $authkey;
				$response = wp_remote_get( $url );
				if ( is_array( $response ) && ! is_wp_error( $response ) ) {
					$r = json_decode( $response['body'] );
					if ( intval( $r->result ) === 0 ) {
						$url        = 'https://' . reset( $r->hosts ) . $r->path;
						$archives[] = array(
							'fileid' => $file_id,
							'name'   => 'restore_' . time() . '.zip',
							'size'   => $r->size,
							'dwlurl' => $url,
						);
						$total_size = $r->size;
					}
				}
			}

			if ( count( $archives ) < 1 ) {
				$result['status'] = 75;
				$result['msg']    = '<p>Failed to get backup file!</p>';
			}

			$op_data = array(
				'operation'   => 'download',
				'state'       => 'init',
				'mode'        => 'manual',
				'archive_num' => 0,
				'archives'    => wp_json_encode( $archives ),
				'offset'      => 0,
				'downloaded'  => 0,
				'total_size'  => $total_size,
			);

			wp2pcloudfuncs::set_operation( $op_data );

		} else {

			$result['status'] = 80;
			$result['msg']    = '<p>File/Folder ID not provided, or maybe hostname is missing!</p>';

		}
	} elseif ( 'get_log' === $m ) {

		$result['perc'] = 0;
		$operation      = wp2pcloudfuncs::get_operation();

		if ( isset( $operation['mode'] ) && 'auto' === $operation['mode'] ) {

			if ( isset( $operation['upload_files'] ) ) {

				$upload_files = trim( $operation['upload_files'] );
				$upload_files = json_decode( $upload_files, true );
				$size         = 0;

				foreach ( $upload_files as $file ) {
					$path = PCLOUD_TEMP_DIR . '/' . $file;
					if ( is_file( $path ) ) {
						$size += filesize( $path );
					}
				}

				$result['offset']    = $operation['offset'];
				$result['size']      = $size;
				$result['sizefancy'] = '~' . round( ( $size / 1024 / 1024 ), 2 ) . ' MB';
				$result['perc']      = 0;

				if ( $size > 0 ) {
					$result['perc'] = round( abs( $result['offset'] / ( $size / 100 ) ), 2 );
				}
			}
		} else {
			$proc                = wp2pcl_event_processor();
			$result['operation'] = $operation;
			$result              = $proc['result'];
		}

		$result['hasactivity'] = wp2pcloudfuncs::get_storred_val( PCLOUD_HAS_ACTIVITY, '0' );

		if ( $dbg_mode ) {
			$result['log'] = wp2pclouddebugger::read_last_log( false );
		} else {
			$result['log'] = wp2pcloudlogger::read_last_log( false );
		}

		$quota     = wp2pcloudfuncs::get_storred_val( PCLOUD_QUOTA, '1' );
		$usedquota = wp2pcloudfuncs::get_storred_val( PCLOUD_USEDQUOTA, '1' );

		if ( $quota > 0 && $usedquota > 0 ) {
			$perc                = round( ( $usedquota / ( $quota / 100 ) ), 2 );
			$result['quotaperc'] = $perc;
		}

		if ( isset( $operation['mode'] ) && 'nothing' !== $operation['mode'] ) {
			$result['operation'] = $operation;
		}

		// If strategy - auto, remove the progress bar!
		if ( isset( $operation['mode'] ) && 'auto' === $operation['mode'] ) {
			$result['percdbg'] = $result['perc'];
			unset( $result['perc'] );
		}

		$result['memlimit']    = ( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '---' );
		$result['memlimitini'] = ini_get( 'memory_limit' );
		$result['failures']    = $operation['failures'] ?? 0;
		$result['maxfailures'] = intval( wp2pcloudfuncs::get_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME ) );

	} elseif ( 'check_can_restore' === $m ) {

		$pl_dir_arr = dirname( __FILE__ );

		if ( ! is_writable( $pl_dir_arr . '/' ) ) {
			$result['status'] = 80;
			$result['msg']    = '<p>Path ' . $pl_dir_arr . '/ is not writable!</p>';
		} elseif ( ! is_writable( sys_get_temp_dir() ) ) {
			$result['status'] = 82;
			$result['msg']    = '<p>Path ' . sys_get_temp_dir() . ' is not writable!</p>';
		} else {
			$result['status'] = 0;
		}
	} elseif ( 'start_backup' === $m ) {

		wp2pcloudfuncs::set_storred_val( PCLOUD_LAST_BACKUPDT, time() );
		wp2pcloudfuncs::set_storred_val( PCLOUD_HAS_ACTIVITY, '1' );

		wp2pclouddebugger::generate_new( 'start_backup at: ' . gmdate( 'Y-m-d H:i:s' ) . ' | instance: ' . $sitename );

		$memlimit    = ( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '---' );
		$memlimitini = ini_get( 'memory_limit' );

		wp2pclouddebugger::log( 'Memory limits: ' . $memlimit . ' / ' . $memlimitini );

		wp2pcl_perform_manual_backup();

		echo '{}';
		die();

	}

	$result['sitename'] = $sitename;

	echo wp_json_encode( $result );
	die();
}


/**
 * This function handles the processes required by the plugin
 *
 * @throws Exception Standart exception will be thrown.
 */
function wp2pcl_event_processor(): array {

	global $plugin_path_base;

	$result = array(
		'status'  => 1, // 0: OK, 1+: error
		'message' => '',
	);

	$operation = wp2pcloudfuncs::get_operation();

	if ( 'upload' === $operation['operation'] ) {
		wp2pclouddebugger::log( 'uploading' );
	} else {
		if ( 'nothing' !== $operation['operation'] ) {
			wp2pclouddebugger::log( 'wp2pcl_event_processor() - op:' . $operation['operation'] );
		}
	}

	if ( isset( $operation['operation'] ) ) {

		if ( isset( $operation['cleanat'] ) ) {

			unset( $operation['perc'] );

			if ( time() > $operation['cleanat'] ) {
				wp2pcloudlogger::clear_log();
				wp2pcloudfuncs::set_storred_val( PCLOUD_HAS_ACTIVITY, '0' );
			}
		} else {

			if ( 'upload' === $operation['operation'] || 'download' === $operation['operation'] ) {
				wp2pcloudfuncs::set_execution_limits();
			}

			if ( 'upload' === $operation['operation'] && 'ready_to_push' === $operation['state'] ) {

				wp2pclouddebugger::log( 'Upload: ready_to_push!<br/>' );

				$operation['state'] = 'uploading_chunks';
				wp2pcloudfuncs::set_operation( $operation );

			} elseif ( 'upload' === $operation['operation'] && 'preparing' === $operation['state'] ) {

				$operation['failures'] += 1;

				wp2pcloudfuncs::set_operation( $operation );

				$max_num_failures = intval( wp2pcloudfuncs::get_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME ) );

				if ( $operation['failures'] > $max_num_failures ) {

					wp2pclouddebugger::log( '== ERROR == Too many failures ( ' . $operation['failures'] . ' / ' . $max_num_failures . ' ), leaving.. !' );

					wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='too_many_failures'>ERROR: Too many failures, try to disable/enable the plugin !</span>" );
					wp2pcloudfuncs::set_operation();

					if ( isset( $operation['mode'] ) && 'auto' === $operation['mode'] ) {
						wp2pcloudfuncs::set_storred_val( PCLOUD_LAST_BACKUPDT, time() - 5 );
					}
				}
			} elseif ( 'upload' === $operation['operation'] && 'uploading_chunks' === $operation['state'] ) {

				$upload_files = trim( $operation['upload_files'] );
				$current_file = intval( $operation['current_file'] );
				$folder_id    = intval( $operation['folder_id'] );
				$upload_id    = intval( $operation['upload_id'] );
				$offset       = intval( $operation['offset'] );
				$upload_files = json_decode( $upload_files, true );

				if ( 1 > count( $upload_files ) ) {

					wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='err_no_archive_files_found'>ERROR: No Archive files found!</span>" );
					wp2pcloudfuncs::set_operation();

					$result['newoffset'] = $offset + 99999;

					if ( isset( $operation['mode'] ) && 'auto' === $operation['mode'] ) {
						wp2pcloudfuncs::set_storred_val( PCLOUD_LAST_BACKUPDT, time() - 5 );
					}

					wp2pcloudfuncs::set_operation();

				} else {

					if ( ! isset( $upload_files[ $current_file ] ) ) {

						$operation['current_file'] = -1;
						wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='upload_completed'>Upload completed!</span>" );
						wp2pclouddebugger::log( 'UPLOAD COMPLETED, scheduler should be OFF!' );

						$file_op = new wp2pcloudfilebackup( $plugin_path_base );
						$file_op->clear_all_tmp_files();

						if ( isset( $operation['mode'] ) && 'auto' === $operation['mode'] ) {
							wp2pcloudfuncs::set_storred_val( PCLOUD_LAST_BACKUPDT, time() );
						}

						wp2pcloudfuncs::set_operation();

					} else {

						$selected_file = $upload_files[ $current_file ];

						$path = rtrim( $plugin_path_base, '/' ) . '/tmp/' . $selected_file;

						$size = abs( filesize( $path ) );

						$result['offset']    = $offset;
						$result['size']      = $size;
						$result['sizefancy'] = '~' . round( ( $size / 1024 / 1024 ), 2 ) . ' MB';

						if ( 'OK' === $operation['chunkstate'] ) {

							$operation['chunkstate'] = 'uploading';

							wp2pcloudfuncs::set_operation( $operation );

							$file_op = new wp2pcloudfilebackup( $plugin_path_base );

							if ( isset( $operation['mode'] ) && 'manual' === $operation['mode'] ) {
								$newoffset = $file_op->upload_chunk( $path, $folder_id, $upload_id, $offset, $operation['failures'] );
							} else {
								$time_limit = ini_get( 'max_execution_time' );
								if ( ! is_bool( $time_limit ) && intval( $time_limit ) <= 0 ) {
									$newoffset = $file_op->upload( $path, $folder_id, $upload_id, $offset );
								} else {
									$newoffset = $file_op->upload_chunk( $path, $folder_id, $upload_id, $offset, $operation['failures'] );
								}
							}

							$result['newoffset']     = $newoffset;
							$operation['chunkstate'] = 'OK';

						} else {
							$result['newoffset'] = $offset;
							$newoffset           = $offset;
						}

						if ( $newoffset <= $offset ) {
							if ( ! isset( $operation['failures'] ) ) {
								$operation['failures'] = 1;
							}
							$operation['failures'] ++;
						} else {
							$operation['failures'] = 0;
						}

						if ( $newoffset > 0 ) {

							$operation['offset'] = $newoffset;
							$result['perc']      = 0;

							if ( $size > 0 ) {
								$result['perc'] = round( abs( $newoffset / ( $size / 100 ) ), 2 );
							}
						}

						wp2pcloudfuncs::set_operation( $operation );

						$max_num_failures = intval( wp2pcloudfuncs::get_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME ) );

						if ( $operation['failures'] > $max_num_failures ) {

							$operation['current_file'] = -1;

							wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='too_many_failures'>ERROR: Too many failures, try to disable/enable the plugin !</span>" );
							wp2pcloudfuncs::set_operation();

							$file_op = new wp2pcloudfilebackup( $plugin_path_base );
							$file_op->clear_all_tmp_files();

							if ( isset( $operation['mode'] ) && 'auto' === $operation['mode'] ) {

								wp2pcloudfuncs::set_storred_val( PCLOUD_LAST_BACKUPDT, time() );

								wp2pclouddebugger::log( 'UPLOAD COMPLETED, scheduler should be OFF!' );
							}
						} else {

							if ( $newoffset >= $size ) {

								$filename = basename( $upload_files[ $current_file ] );

								$file_op = new wp2pcloudfilebackup( $plugin_path_base );
								$file_op->save( $upload_id, $filename, $folder_id );

								wp2pclouddebugger::log( '[ ' . $current_file . ' ] File upload completed!' );

								$new_file_index = $current_file + 1;

								if ( isset( $upload_files[ $new_file_index ] ) ) {

									wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='upload_completed_wait_next' style='color: green'>File upload completed! Please wait for the next file to be uploaded!</span>" );

									$upload = $file_op->create_upload();

									if ( ! is_object( $upload ) || ! property_exists( $upload, 'uploadid' ) ) {
										wp2pclouddebugger::log( 'File -> upload -> "createUpload" not returning the expected data!' );
										throw new Exception( 'File -> upload -> "createUpload" not returning the expected data!' );
									} else {
										$operation['current_file'] = $new_file_index;
										$operation['upload_id']    = $upload->uploadid;
										$operation['failures']     = 0;
										$operation['offset']       = 0;
									}

									wp2pcloudfuncs::set_operation( $operation );

								} else {

									wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='upload_completed'>Upload completed!</span>" );
									wp2pclouddebugger::log( 'UPLOAD COMPLETED, scheduler should be OFF!' );

									$file_op = new wp2pcloudfilebackup( $plugin_path_base );
									$file_op->clear_all_tmp_files();

									if ( isset( $operation['mode'] ) && 'auto' === $operation['mode'] ) {
										wp2pcloudfuncs::set_storred_val( PCLOUD_LAST_BACKUPDT, time() );
									}

									wp2pcloudfuncs::set_operation();
								}
							}
						}
					}
				}
			}

			if ( 'download' === $operation['operation'] && 'init' === $operation['state'] ) {

				$operation['state'] = 'download_chunks';
				wp2pcloudfuncs::set_operation( $operation );

			} elseif ( 'download' === $operation['operation'] && 'extract' === $operation['state'] ) {

				wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='start_extr_file_folders'>Start extracting files and folders, please wait...</span>" );

				$file_op = new wp2pcloudfilerestore();

				$archives = json_decode( $operation['archives'], true );
				foreach ( $archives as $archive ) {
					$file_op->extract( PCLOUD_TEMP_DIR . '/' . $archive['name'] );
				}

				$operation['state'] = 'restoredb';
				wp2pcloudfuncs::set_operation( $operation );

			} elseif ( 'download' === $operation['operation'] && 'restoredb' === $operation['state'] ) {

				wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='start_extr_db'>Start reconstructing the database, please wait...</span>" );

				$file_op = new wp2pcloudfilerestore();
				$file_op->restore_db();

				$operation['state'] = 'cleanup';
				wp2pcloudfuncs::set_operation( $operation );

			} elseif ( 'download' === $operation['operation'] && 'cleanup' === $operation['state'] ) {

				wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='clean_up_pls_wait'>Cleaning up, please wait...</span>" );

				$file_op = new wp2pcloudfilerestore();

				$archives = json_decode( $operation['archives'], true );
				foreach ( $archives as $archive ) {
					$file_op->remove_files( PCLOUD_TEMP_DIR . '/' . $archive['name'] );
				}

				wp2pcloudfuncs::set_operation();

				wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='bk_restored'>Backup - restored! You can refresh the page now!</span>" );

			} elseif ( 'download' === $operation['operation'] && 'download_chunks' === $operation['state'] ) {

				if ( PCLOUD_DEBUG ) {
					$result['msg'] = 'Download chunks ...!';
				}

				$offset      = intval( $operation['offset'] );
				$archives    = trim( $operation['archives'] );
				$archive_num = intval( $operation['archive_num'] );
				$total_size  = intval( $operation['total_size'] );
				$archives    = json_decode( $archives, true );

				if ( 1 > count( $archives ) ) {

					wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='failed_no_archive_file_to_download'>ERROR: No Archive to download!</span>" );
					wp2pcloudfuncs::set_operation();

					$result['newoffset'] = $offset + 99999;

					if ( isset( $operation['mode'] ) && 'auto' === $operation['mode'] ) {
						wp2pcloudfuncs::set_storred_val( PCLOUD_LAST_BACKUPDT, time() - 5 );
					}

					wp2pcloudfuncs::set_operation();

				} elseif ( ! isset( $archives[ $archive_num ] ) ) {

					wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='dwl_completed'>Download completed!</span>" );
					wp2pcloudlogger::info( "<span class='pcl_transl' data-i10nk='unzip_pls_wait'>Unzipping the archive, please wait:</span>" );

					$operation['state'] = 'extract';
					wp2pcloudfuncs::set_operation( $operation );

				} else {

					$archive = $archives[ $archive_num ];

					$dwlurl              = trim( $archive['dwlurl'] );
					$size                = intval( $archive['size'] );
					$archive_name        = PCLOUD_TEMP_DIR . '/' . trim( $archive['name'] );
					$result['offset']    = $offset;
					$result['size']      = $size;
					$result['sizefancy'] = '~' . round( ( $total_size / 1024 / 1024 ), 2 ) . ' MB';

					$file_op             = new wp2pcloudfilerestore();
					$newoffset           = $file_op->download_chunk_curl( $dwlurl, $offset, $archive_name );
					$result['newoffset'] = $newoffset;

					$operation['downloaded'] += $newoffset - $offset;

					if ( $newoffset > 0 ) {

						$operation['offset'] = $newoffset;

						$result['perc'] = 0;
						if ( $size > 0 ) {
							$result['perc'] = round( abs( $operation['downloaded'] / ( $operation['total_size'] / 100 ) ), 2 );
						}
					}

					if ( $newoffset > $size ) {
						$operation['archive_num'] = $archive_num + 1;
						$operation['offset']      = 0;
					}

					wp2pcloudfuncs::set_operation( $operation );
				}
			}

			if ( isset( $result['perc'] ) && $result['perc'] > 100 ) {
				$result['perc'] = 100;
			}
		}
	}

	return array(
		'operation' => $operation,
		'result'    => $result,
	);
}

/**
 * Start manual backup procedure
 *
 * @throws Exception Standart exception will be thrown.
 */
function wp2pcl_perform_manual_backup(): void {

	global $plugin_path_base;

	wp2pcloudfuncs::set_execution_limits();

	wp2pcloudlogger::generate_new( "<span class='pcl_transl' data-i10nk='start_backup_at'>Start backup at</span> " . gmdate( 'Y-m-d H:i:s' ) );

	$f = new wp2pcloudfilebackup( $plugin_path_base );

	$wp2pcl_withmysql = wp2pcloudfuncs::get_storred_val( PCLOUD_SCHDATA_INCLUDE_MYSQL );
	if ( ! empty( $wp2pcl_withmysql ) && 1 === intval( $wp2pcl_withmysql ) ) {
		wp2pclouddebugger::log( 'Database backup will start now!' );
		$b    = new wp2pclouddbbackup();
		$file = $b->start();

		if ( ! is_bool( $file ) ) {
			$f->set_mysql_backup_filename( $file );
			wp2pclouddebugger::log( 'Database backup - ready!' );
		} else {
			wp2pclouddebugger::log( 'Database backup - failed!' );
			wp2pcloudlogger::info( "<span style='color: red' class='pcl_transl' data-i10nk='failed_to_backup_db'>Database backup - failed!</span>" );
		}
	}

	wp2pclouddebugger::log( 'File backup will start now!' );

	$f->start();
}


/**
 * This function performce auto-backup
 *
 * @throws Exception Standart exception will be thrown.
 */
function wp2pcl_perform_auto_backup(): void {

	global $plugin_path_base;

	$operation = wp2pcloudfuncs::get_operation();

	if ( 'init' === $operation['state'] ) {

		pcl_verify_directory_structure();

		wp2pclouddebugger::log( 'wp2pcl_perform_auto_backup() - op:init !' );

		wp2pcloudlogger::generate_new( "<span class='pcl_transl' data-i10nk='start_auto_backup_at'>Start auto backup at</span> " . gmdate( 'Y-m-d H:i:s' ) );

		$f = new wp2pcloudfilebackup( $plugin_path_base );

		$wp2pcl_withmysql = wp2pcloudfuncs::get_storred_val( PCLOUD_SCHDATA_INCLUDE_MYSQL );
		if ( ! empty( $wp2pcl_withmysql ) && 1 === intval( $wp2pcl_withmysql ) ) {
			$b    = new wp2pclouddbbackup();
			$file = $b->start();
			$f->set_mysql_backup_filename( $file );
		}

		$f->start( 'auto' );

		wp2pcloudfuncs::set_storred_val( PCLOUD_HAS_ACTIVITY, '1' );

	} else {

		wp2pclouddebugger::log( 'wp2pcl_perform_auto_backup() - op:processor !' );

		wp2pcl_event_processor();

	}
}


/**
 * Auto-backup hook function
 *
 * @throws Exception Standart exception will be thrown.
 */
function wp2pcl_run_pcloud_backup_hook(): void {

	$lastbackupdt_tm = intval( wp2pcloudfuncs::get_storred_val( PCLOUD_LAST_BACKUPDT ) );

	$freq        = wp2pcloudfuncs::get_storred_val( PCLOUD_SCHDATA_KEY );
	$after_hour  = wp2pcloudfuncs::get_storred_val( PCLOUD_SCHHOUR_FROM_KEY );
	$before_hour = wp2pcloudfuncs::get_storred_val( PCLOUD_SCHHOUR_TO_KEY );

	$rejected = false;

	if ( $lastbackupdt_tm > 0 ) {

		if ( '2_minute' === $freq ) {
			if ( $lastbackupdt_tm > ( time() - 120 ) ) {
				$rejected = true;
			}
		} elseif ( '1_hour' === $freq ) {
			if ( $lastbackupdt_tm > ( time() - 3600 ) ) {
				$rejected = true;
			}
		} elseif ( '4_hours' === $freq ) {
			if ( $lastbackupdt_tm > ( time() - ( 3600 * 4 ) ) ) {
				$rejected = true;
			}
		} elseif ( 'daily' === $freq ) {
			if ( $lastbackupdt_tm > ( time() - 86400 ) ) {
				$rejected = true;
			}
		} elseif ( 'weekly' === $freq ) {
			if ( $lastbackupdt_tm > strtotime( '-1 week' ) ) {
				$rejected = true;
			}
		} elseif ( 'monthly' === $freq ) {
			if ( $lastbackupdt_tm > strtotime( '-1 month' ) ) {
				$rejected = true;
			}
		} else { // Unexpected value for $freq. or none, skipping.
			$rejected = true;
		}
	}

	$current_hour = intval( gmdate( 'H' ) );
	$after_hour   = intval( $after_hour );
	$before_hour  = intval( $before_hour );

	if ( $after_hour >= 0 && $current_hour < $after_hour ) {
		$rejected = true;
	}
	if ( $before_hour >= 0 && $current_hour >= $before_hour ) {
		$rejected = true;
	}

	$operation = wp2pcloudfuncs::get_operation();

	if ( $rejected ) {

		if ( isset( $operation['operation'] ) && ( 'upload' === $operation['operation'] ) && ( 'auto' === $operation['mode'] ) ) {
			wp2pcloudfuncs::set_operation();
		}

		return;
	}

	if ( isset( $operation['operation'] ) && ( 'nothing' === $operation['operation'] ) ) {

		wp2pclouddebugger::log( 'wp2pcl_run_pcloud_backup_hook() - op:nothing, going to init !' );

		$op_data = array(
			'operation'  => 'upload',
			'state'      => 'init',
			'mode'       => 'auto',
			'status'     => '',
			'chunkstate' => 'OK',
			'failures'   => 0,
			'folder_id'  => 0,
			'offset'     => 0,
		);

		$json_data = wp_json_encode( $op_data );

		wp2pcloudfuncs::set_storred_val( 'wp2pcl_operation', $json_data );

		if ( ! wp_next_scheduled( 'init_autobackup' ) ) { // This will always be false.
			wp_schedule_event( time(), '10_sec', 'init_autobackup', array( false ) );
		}
	} else {

		wp2pclouddebugger::log( 'wp2pcl_run_pcloud_backup_hook() - uploading... ' );

		wp2pcl_perform_auto_backup();
	}
}

/**
 * This function calls the settings page file and loads some JS and CSS files
 *
 * @throws Exception Standart exception will be thrown.
 * @noinspection PhpUnused
 */
function wp2pcloud_display_settings(): void {

	if ( ! extension_loaded( 'zip' ) ) {
		print( '<h2 style="color: red">PHP ZIP extension not loaded</h2><small>Please, contact the server administrator!</small>' );
		return;
	}

	$do         = '';
	$auth_key   = '';
	$locationid = 1;

	if ( isset( $_GET['do'] ) ) { // phpcs:ignore
		$do = sanitize_text_field( wp_unslash( $_GET['do'] ) ); // phpcs:ignore
	}
	if ( isset( $_GET['access_token'] ) ) { // phpcs:ignore
		$auth_key = trim( sanitize_text_field( wp_unslash( $_GET['access_token'] ) ) ); // phpcs:ignore
	}
	if ( isset( $_GET['locationid'] ) ) { // phpcs:ignore
		$locationid = intval( sanitize_key( wp_unslash( $_GET['locationid'] ) ) ); // phpcs:ignore
	}

	if ( ( 'pcloud_auth' === $do ) && ! empty( $auth_key ) ) {

		if ( $locationid > 0 && $locationid < 100 ) {
			wp2pcloudfuncs::set_storred_val( PCLOUD_API_LOCATIONID, $locationid );
			$result['status'] = 0;
		}

		wp2pcloudfuncs::set_storred_val( PCLOUD_AUTH_KEY, $auth_key );

		pcl_verify_directory_structure();

		print '<h2 style="color: green;text-align: center" class="wp2pcloud-login-succcess">You are successfully logged in!</h2>';

	}

	$static_files_ver = '2.0.0.1';

	wp_enqueue_script( 'wp2pcl-scr', plugins_url( '/assets/js/wp2pcl.js', __FILE__ ), array(), $static_files_ver, true );
	wp_enqueue_style( 'wpb2pcloud', plugins_url( '/assets/css/wpb2pcloud.css', __FILE__ ), array(), $static_files_ver );

	$auth_key = wp2pcloudfuncs::get_storred_val( PCLOUD_AUTH_KEY );

	$data = array(
		'pcloud_auth'       => $auth_key,
		'blog_name'         => get_bloginfo( 'name' ),
		'blog_url'          => get_bloginfo( 'url' ),
		'archive_icon'      => plugins_url( '/assets/img/zip.png', __FILE__ ),
		'api_hostname'      => wp2pcloudfuncs::get_api_ep_hostname(),
		'PCLOUD_BACKUP_DIR' => PCLOUD_BACKUP_DIR,
	);

	wp_localize_script( 'wp2pcl-scr', 'php_data', $data );

	$plugin_path = plugins_url( '/', __FILE__ );

	include 'views/wp2pcl-config.php';
}

/**
 * This function will be called after the plugins is installed
 *
 * @return void
 * @noinspection PhpUnused
 */
function wp2pcl_install(): void {

	global $max_num_failures;

	wp2pcloudfuncs::get_storred_val( PCLOUD_API_LOCATIONID, '1' );
	wp2pcloudfuncs::get_storred_val( PCLOUD_AUTH_KEY );
	wp2pcloudfuncs::get_storred_val( PCLOUD_AUTH_MAIL );
	wp2pcloudfuncs::get_storred_val( PCLOUD_SCHDATA_KEY, 'daily' );
	wp2pcloudfuncs::get_storred_val( PCLOUD_SCHHOUR_FROM_KEY, '-1' );
	wp2pcloudfuncs::get_storred_val( PCLOUD_SCHHOUR_TO_KEY, '-1' );
	wp2pcloudfuncs::get_storred_val( PCLOUD_SCHDATA_INCLUDE_MYSQL, '1' );
	wp2pcloudfuncs::get_storred_val( PCLOUD_OPERATION );
	wp2pcloudfuncs::get_storred_val( PCLOUD_HAS_ACTIVITY, '0' );
	wp2pcloudfuncs::get_storred_val( PCLOUD_LOG );
	wp2pcloudfuncs::get_storred_val( PCLOUD_DBG_LOG );
	wp2pcloudfuncs::get_storred_val( PCLOUD_NOTIFICATIONS );
	wp2pcloudfuncs::get_storred_val( PCLOUD_LAST_BACKUPDT, strval( time() ) );
	wp2pcloudfuncs::get_storred_val( PCLOUD_QUOTA, '1' );
	wp2pcloudfuncs::get_storred_val( PCLOUD_USEDQUOTA, '1' );
	wp2pcloudfuncs::get_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME, strval( $max_num_failures ) );
	wp2pcloudfuncs::get_storred_val( PCLOUD_ASYNC_UPDATE_VAL );
	wp2pcloudfuncs::get_storred_val( PCLOUD_BACKUP_FILE_INDEX );
	wp2pcloudfuncs::get_storred_val( PCLOUD_OAUTH_CLIENT_ID );
	wp2pcloudfuncs::get_storred_val( PCLOUD_TEMP_DIR );
	wp2pcloudfuncs::get_storred_val( PCLOUD_PLUGIN_MIN_PHP_VERSION );

	add_filter(
		'cron_schedules',
		function ( $schedules ) {
			$schedules['10_sec']   = array(
				'interval' => 10,
				'display'  => __( '10 seconds' ),
			);
			$schedules['2_minute'] = array(
				'interval' => 120,
				'display'  => __( '2 minute' ),
			);
			$schedules['1_hour']   = array(
				'interval' => 3600,
				'display'  => __( '1 hour' ),
			);
			$schedules['4_hours']  = array(
				'interval' => 3600 * 4,
				'display'  => __( '4 hours' ),
			);

			return $schedules;
		}
	);

	wp_schedule_event( time(), '2_minute', 'init_autobackup', array( false ) );
}

/**
 * Cleaning up after uninstall of the plugin
 *
 * @return void
 * @noinspection PhpUnused
 */
function wp2pcl_uninstall(): void {

	delete_option( PCLOUD_API_LOCATIONID );
	delete_option( PCLOUD_AUTH_KEY );
	delete_option( PCLOUD_AUTH_MAIL );
	delete_option( PCLOUD_SCHDATA_KEY );
	delete_option( PCLOUD_SCHHOUR_FROM_KEY );
	delete_option( PCLOUD_SCHHOUR_TO_KEY );
	delete_option( PCLOUD_SCHDATA_INCLUDE_MYSQL );
	delete_option( PCLOUD_OPERATION );
	delete_option( PCLOUD_HAS_ACTIVITY );
	delete_option( PCLOUD_LOG );
	delete_option( PCLOUD_DBG_LOG );
	delete_option( PCLOUD_NOTIFICATIONS );
	delete_option( PCLOUD_LAST_BACKUPDT );
	delete_option( PCLOUD_MAX_NUM_FAILURES_NAME );
	delete_option( PCLOUD_QUOTA );
	delete_option( PCLOUD_USEDQUOTA );
	delete_option( PCLOUD_ASYNC_UPDATE_VAL );
	delete_option( PCLOUD_BACKUP_FILE_INDEX );
	delete_option( PCLOUD_OAUTH_CLIENT_ID );
	delete_option( PCLOUD_TEMP_DIR );
	delete_option( PCLOUD_PLUGIN_MIN_PHP_VERSION );
	wp_clear_scheduled_hook( 'init_autobackup' );
	spl_autoload_unregister( '\Pcloud\Autoloader::loader' );
}

/**
 * This func creates
 *
 * @param array|null $schedules Array of previews schedulles.
 *
 * @return array
 * @noinspection PhpUnused
 */
function backup_to_pcloud_cron_schedules( ?array $schedules ): array {

	$new_schedules = array(
		'30_sec'   => array(
			'interval' => 30,
			'display'  => __( '30 seconds' ),
		),
		'2_minute' => array(
			'interval' => 120,
			'display'  => __( '2 minute' ),
		),
		'1_hour'   => array(
			'interval' => 3600,
			'display'  => __( '1 hour' ),
		),
		'4_hours'  => array(
			'interval' => 3600 * 4,
			'display'  => __( '4 hours' ),
		),
		'daily'    => array(
			'interval' => 86400,
			'display'  => __( 'Daily' ),
		),
		'weekly'   => array(
			'interval' => 604800,
			'display'  => __( 'Weekly' ),
		),
		'monthly'  => array(
			'interval' => 2592000,
			'display'  => __( 'Monthly' ),
		),
	);

	return array_merge( $schedules, $new_schedules );
}

/**
 * Verify that the folder exists on pCloud servers.
 *
 * @return void
 */
function pcl_verify_directory_structure(): void {

	$authkey = wp2pcloudfuncs::get_storred_val( PCLOUD_AUTH_KEY );
	if ( ! is_string( $authkey ) || empty( $authkey ) ) {
		return;
	}

	$hostname = wp2pcloudfuncs::get_api_ep_hostname();
	if ( empty( $hostname ) ) {
		return;
	}

	$backup_file_index = wp2pcloudfuncs::get_storred_val( PCLOUD_BACKUP_FILE_INDEX );
	if ( empty( $backup_file_index ) ) {
		$backup_file_index = time();
		wp2pcloudfuncs::set_storred_val( PCLOUD_BACKUP_FILE_INDEX, $backup_file_index );
	}

	$apiep = 'https://' . rtrim( $hostname );
	$url   = $apiep . '/listfolder?path=/' . PCLOUD_BACKUP_DIR . '&access_token=' . $authkey;

	$response = wp_remote_get( $url );
	if ( is_array( $response ) && ! is_wp_error( $response ) ) {
		$response_body_list = json_decode( $response['body'] );
		if ( property_exists( $response_body_list, 'result' ) ) {
			$resp_result = intval( $response_body_list->result );
			if ( 2005 === $resp_result ) {

				$backup_directories = explode( '/', PCLOUD_BACKUP_DIR );

				if ( is_array( $backup_directories ) && 0 < count( $backup_directories ) ) {
					$url                       = $apiep . '/createfolder?path=/' . $backup_directories[0] . '&name=' . $backup_directories[0] . '&access_token=' . $authkey;
					$response_main_folder      = wp_remote_get( $url );
					$response_main_folder_body = json_decode( $response_main_folder['body'] );
					if ( property_exists( $response_main_folder_body, 'result' ) && ( 0 === intval( $response_main_folder_body->result ) ) ) {
						$url = $apiep . '/createfolder?path=/' . PCLOUD_BACKUP_DIR . '&name=' . $backup_directories[1] . '&access_token=' . $authkey;
						wp_remote_get( $url );
					}
				}
			}
		}
	}
}

add_filter( 'cron_schedules', 'backup_to_pcloud_cron_schedules' );

if ( ! function_exists( 'wp2pcl_load_scripts' ) ) {

	/**
	 * We are attempting to load main plugin js file.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	function wp2pcl_load_scripts(): void {
		wp_register_script( 'wp2pcl-wp2pcljs', plugins_url( '/assets/js/wp2pcl.js', __FILE__ ), array(), '2.0.0.1', true );
		wp_enqueue_script( 'jquery' );
	}
}

if ( ! function_exists( 'pcloud_plugin_check' ) ) {

	/**
	 * Check if the PHP version is compatible with the plugin.
	 *
	 * @return void
	 */
	function pcloud_plugin_check(): void {
		if ( version_compare( PHP_VERSION, PCLOUD_PLUGIN_MIN_PHP_VERSION, '<' ) ) {
			// Deactivate the plugin if the current PHP version is lower than the required.
			deactivate_plugins( plugin_basename( __FILE__ ) );
			// Display an error message to the admin.
			add_action( 'admin_notices', 'pcloud_plugin_php_version_error' );
		}

		$current_limit = WP2PcloudFuncs::get_memory_limit();
		if ( $current_limit < 64 ) {
			add_action( 'admin_notices', 'pcloud_plugin_php_memory_limit_error' );
		}
	}
}

/**
 * Error notice for admins if PHP version is too low.
 */
if ( ! function_exists( 'pcloud_plugin_php_version_error' ) ) {

	/**
	 * Function to display an error message if the PHP version is too low.
	 *
	 * @return void
	 */
	function pcloud_plugin_php_version_error(): void {
		$message = sprintf(
			'[pCloud WP Backup] Your PHP version is %s, but the Your Plugin Name requires at least PHP %s to run. Please update PHP or contact your hosting provider for assistance.',
			PHP_VERSION,
			PCLOUD_PLUGIN_MIN_PHP_VERSION
		);
		printf( '<div class="error"><p>%s</p></div>', esc_html( $message ) );
	}
}

/**
 * Error notice for admins if PHP Memory Limit is too low.
 */
if ( ! function_exists( 'pcloud_plugin_php_memory_limit_error' ) ) {

	/**
	 * Function to display an error message if the PHP memory limit is too low.
	 *
	 * @return void
	 */
	function pcloud_plugin_php_memory_limit_error(): void {
		$current_limit = WP2PcloudFuncs::get_memory_limit();
		$message       = sprintf(
			"[pCloud WP Backup] Your PHP 'memory_limit' setting is currently too low at [ %dM ]; it must be at least 64Mb for the plugin to function properly.",
			$current_limit
		);
		printf( '<div class="error"><p>%s</p></div>', esc_html( $message ) );
	}
}

// Hook into 'admin_init' to check PHP version as early as possible.
add_action( 'admin_init', 'pcloud_plugin_check' );

register_activation_hook( __FILE__, 'wp2pcl_install' );
register_deactivation_hook( __FILE__, 'wp2pcl_uninstall' );
add_action( 'admin_menu', 'backup_to_pcloud_admin_menu' );
add_action( 'wp_enqueue_scripts', 'wp2pcl_load_scripts' );
add_action( 'init_autobackup', 'wp2pcl_run_pcloud_backup_hook' );
if ( is_admin() ) {
	add_action( 'wp_ajax_pcloudbackup', 'wp2pcl_ajax_process_request' );
}

if( ! function_exists( 'debug_wp_remote_post_and_get_request' ) ) :
	function debug_wp_remote_post_and_get_request( $response, $context, $class, $request, $url ): void {

		if (str_contains($url, 'pcloud')) {
			error_log( '------------------------------------------------------------------------------------------' );
			error_log( 'URL: ' . $url );
			error_log( 'Request: ' . json_encode( $request ) );
			error_log( 'Response: ' . json_encode( $response ) );
			// error_log( $context );
		}
	}
	add_action( 'http_api_debug', 'debug_wp_remote_post_and_get_request', 10, 5 );
endif;