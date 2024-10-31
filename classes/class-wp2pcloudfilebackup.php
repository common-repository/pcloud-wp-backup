<?php
/**
 * WP2pCloudFileBackUp class
 *
 * @file class-wp2pcloudfilebackup.php
 * @package pcloud_wp_backup
 */

/**
 * Class WP2pCloudFileBackUp
 */
class WP2pCloudFileBackUp {

	/**
	 * Authentication key
	 *
	 * @var string $authkey API authentication key.
	 */
	private $authkey;

	/**
	 * API endpoint
	 *
	 * @var string $apiep API endpoint
	 */
	private $apiep;

	/**
	 * Backup File name
	 *
	 * @var string $sql_backup_file SQL backup file name
	 */
	private $sql_backup_file;

	/**
	 * Skip this folder on backup
	 *
	 * @var string[] $skip_folders
	 */
	private $skip_folders = array( '.idea', '.code', 'wp-pcloud-backup', 'wp2pcloud_tmp' );

	/**
	 * The size in bytes of each uploaded/downloaded chunk
	 *
	 * @var int $part_size
	 */
	private $part_size;

	/**
	 * Base plugin dir
	 *
	 * @var string $base_dir
	 */
	private $base_dir;

	/**
	 * Class contructor
	 *
	 * @param string $base_dir Base directory.
	 */
	public function __construct( $base_dir ) {

		$this->sql_backup_file = '';
		$this->base_dir        = $base_dir;
		$this->authkey         = WP2pCloudFuncs::get_storred_val( PCLOUD_AUTH_KEY );
		$this->apiep           = rtrim( 'https://' . WP2pCloudFuncs::get_api_ep_hostname() );
		$this->part_size       = 3 * 1000 * 1000;

		$this_dirs_path = explode( DIRECTORY_SEPARATOR, __DIR__ );
		if ( is_array( $this_dirs_path ) && count( $this_dirs_path ) > 2 ) {
			if ( isset( $this_dirs_path[ count( $this_dirs_path ) - 2 ] ) ) {
				$plugin_dir = $this_dirs_path[ count( $this_dirs_path ) - 2 ];
				if ( preg_match( '/pcloud/', $plugin_dir ) ) {
					$this->skip_folders[] = $plugin_dir;
				}
			}
		}
	}

	/**
	 * Set MySQL backup file name
	 *
	 * @param string $file_name File name.
	 *
	 * @return void
	 */
	public function set_mysql_backup_filename( $file_name ) {
		$this->sql_backup_file = $file_name;
	}

	/**
	 * Start backup process
	 *
	 * @param string $mode Backup process mode.
	 *
	 * @return void
	 * @throws Exception Standart Exception can be thrown.
	 */
	public function start( $mode = 'manual' ) {

		WP2pCloudFuncs::set_execution_limits();

		if ( ! is_dir( $this->base_dir . '/tmp' ) ) {
			mkdir( $this->base_dir . '/tmp' );
			WP2pCloudDebugger::log( 'TMP directory created!' );
		}

		if ( 'auto' === $mode ) {
			$backup_file_name = $this->base_dir . '/tmp/autoArchive_' . time();
		} else {
			$backup_file_name = $this->base_dir . '/tmp/archive_' . time();
		}

		$backup_file_name = $backup_file_name . '.zip';

		$op_data = array(
			'operation'      => 'upload',
			'state'          => 'preparing',
			'mode'           => $mode,
			'chunkstate'     => 'OK',
			'write_filename' => $backup_file_name,
			'failures'       => 0,
			'folder_id'      => 0,
			'offset'         => 0,
		);
		WP2pCloudFuncs::set_operation( $op_data );

		$this->clear_all_tmp_files();

		WP2pCloudDebugger::log( 'All temporary files - cleared!' );

		$rootdir = rtrim( ABSPATH, '/' );

		WP2pCloudDebugger::log( 'Creating a list of files to be compressed!' );

		$files = self::find_all_files( $rootdir );

		WP2pCloudDebugger::log( 'The List of all files is ready and will be sent for compression!' );

		$php_extensions            = get_loaded_extensions();
		$has_archive_ext_installed = array_search( 'zip', $php_extensions, true );

		if ( $has_archive_ext_installed ) {

			WP2pCloudDebugger::log( 'Start creating ZIP archive!' );

			for ( $try = 0; $try < 5; $try++ ) {

				WP2pCloudDebugger::log( 'Attempt [ ' . ( $try + 1 ) . ' ] to create the ZIP archive!' );

				$zipping_successfull = $this->create_zip( $files, $backup_file_name );
				if ( $zipping_successfull ) {
					break;
				} else {

					$operation             = WP2pCloudFuncs::get_operation();
					$operation['failures'] = 0;
					WP2pCloudFuncs::set_operation( $operation );

					WP2pCloudFuncs::add_item_for_async_update( 'failures', 0 );

					WP2pCloudDebugger::log( 'Closing ZIP archive with attempt: ' . ( $try + 1 ) . ' failed, retrying!' );

					$files = self::find_all_files( $rootdir );
				}
			}

			if ( ! $zipping_successfull ) {

				WP2pCloudFuncs::set_operation( array() );

				WP2pCloudLogger::info( '<span>ERROR: Failed to create backup ZIP file !</span>' );
				WP2pCloudDebugger::log( 'Failed to create valid ZIP archive after all 5 tries!' );

			} else {

				sleep( 3 );

				try {

					WP2pCloudDebugger::log( 'Archive seems ready, trying to validate it!' );

					$this->validate_zip_archive( $backup_file_name );

					WP2pCloudDebugger::log( 'Zip Archive - seems valid!' );

				} catch ( Exception $e ) {

					WP2pCloudLogger::info( '<span>ERROR: Backup archive not valid!</span>' );
					WP2pCloudDebugger::log( 'Invalid backup file detected, error: ' . $e->getMessage() . ' file: ' . $backup_file_name );

					WP2pCloudFuncs::set_operation( array() );

					exit();
				}
			}
		} else {

			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='err_backup_arch_no_file'>ERROR: Backup archive file don't exist!</span>" );
			WP2pCloudDebugger::log( 'Backup file does not exist! PHP Zip extension is missing!' );

			WP2pCloudFuncs::set_operation( array() );

			exit();
		}

		WP2pCloudDebugger::log( 'Archiving process - COMPLETED!' );

		WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='zip_file_created'>Zip file is created! Uploading to pCloud</span>" );

		if ( 'auto' === $mode ) {
			$time_limit = ini_get( 'max_execution_time' );
			if ( ! is_bool( $time_limit ) && intval( $time_limit ) === 0 ) {
				WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='upd_strategy_once'>Upload strategy - at once !</span>" );
				WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='pls_wait_may_take_time'>Please wait, may take time!</span>" );
				WP2pCloudDebugger::log( 'Upload strategy - at once !' );
			} else {
				WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='upd_strategy_chunks'>Upload strategy - chunk by chunk !</span>" );
				WP2pCloudDebugger::log( 'Upload strategy - chunk by chunk !' );
			}
		}

		if ( ! file_exists( $backup_file_name ) ) {

			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='err_backup_arch_no_file'>ERROR: Backup archive file don't exist!</span>" );
			WP2pCloudDebugger::log( 'Backup file does not exist, quiting... !' );

			WP2pCloudFuncs::set_operation( array() );

		} else {

			$folder_id = self::get_upload_dir_id();

			$upload = $this->create_upload();
			if ( ! is_object( $upload ) ) {
				WP2pCloudDebugger::log( 'File -> upload -> "createUpload" not returning the expected data!' );
				throw new Exception( 'File -> upload -> "createUpload" not returning the expected data!' );
			} else {

				$op_data['state']          = 'ready_to_push';
				$op_data['folder_id']      = $folder_id;
				$op_data['upload_id']      = $upload->uploadid;
				$op_data['write_filename'] = $backup_file_name;
				$op_data['failures']       = 0;

				WP2pCloudFuncs::set_operation( $op_data );
			}
		}
	}

	/**
	 * Clear all temporary files
	 *
	 * @return void
	 */
	private function clear_all_tmp_files() {
		$files = glob( $this->base_dir . '/tmp/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) && is_writable( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Collect all files in directory
	 *
	 * @param string $dir Directory to scan for files.
	 *
	 * @return array
	 */
	private function find_all_files( $dir ) {

		if ( in_array( $dir, $this->skip_folders, true ) ) {
			return array();
		}

		$root = scandir( $dir );

		if ( is_array( $root ) ) {

			$result = array();
			foreach ( $root as $value ) {
				if ( '.' === $value || '..' === $value ) {
					continue;
				}
				if ( in_array( $value, $this->skip_folders, true ) ) {
					continue;
				}

				if ( is_file( "$dir/$value" ) ) {
					$result[] = "$dir/$value";
					continue;
				}
				foreach ( self::find_all_files( "$dir/$value" ) as $val ) {
					$result[] = $val;
				}
			}

			return $result;
		}

		return array();
	}

	/**
	 * Create ZIP archive procedure.
	 *
	 * @param array  $files Array of files to be added to the ZIP archive.
	 * @param string $backup_file_name Backup file name.
	 *
	 * @return bool
	 */
	private function create_zip( $files, $backup_file_name ) {

		WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='start_create_zip'>Starting with creating ZIP archive, please wait...</span>" );

		$zip = new ZipArchive();
		if ( true !== $zip->open( $backup_file_name, ZIPARCHIVE::CREATE ) ) {

			$zip->close();
			$zip = null;

			WP2pCloudDebugger::log( 'ZIP state - failed to create the ZIP file, leaving ...!' );

			return false;
		}

		WP2pCloudDebugger::log( 'ZIP state - opened!' );

		$zip->setArchiveComment( 'Wordpress2pCloud-' . gmdate( 'd.m.Y H:i:s' ) );

		$num_files = count( $files );

		foreach ( $files as $file ) {
			if ( file_exists( $file ) && is_readable( $file ) ) {
				$zip->addFile( $file, str_replace( ABSPATH, '', $file ) );
			}
		}

		if ( $num_files > 500000 ) {
			WP2pCloudFuncs::set_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME, 15000 );
		} elseif ( $num_files > 100000 ) {
			WP2pCloudFuncs::set_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME, 6000 );
		} elseif ( $num_files > 40000 ) {
			WP2pCloudFuncs::set_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME, 2000 );
		} elseif ( $num_files > 10000 ) {
			WP2pCloudFuncs::set_storred_val( PCLOUD_MAX_NUM_FAILURES_NAME, 500 );
		}

		WP2pCloudDebugger::log( 'ZIP entries added [ ' . count( $files ) . ' ]' );

		if ( ! empty( $this->sql_backup_file ) ) {
			if ( file_exists( $this->sql_backup_file ) && is_readable( $this->sql_backup_file ) ) {
				$zip->addFile( $this->sql_backup_file, 'backup.sql' );
				WP2pCloudDebugger::log( 'ZIP DB file - added!' );
			}
		}

		WP2pCloudDebugger::log( 'ZIP archive - filling-up and closing' );

		if ( $zip->close() ) {

			$size = WP2pCloudFuncs::format_bytes( filesize( $backup_file_name ) );

			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='backup_file_size'>Backup file size:</span> ( $size )" );
			WP2pCloudDebugger::log( 'ZIP File successfully closed! [ ' . $size . ' ]' );

			$zip = null;

			return true;

		} else {

			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='err_failed2zip'>Error: failed to create zip archive!</span>" );
			WP2pCloudDebugger::log( '--------- |||| -------- Failed to create ZIP file!' );

			$zip = null;
		}

		return false;
	}

	/**
	 * Create remote directory
	 *
	 * @param string $dir_name Remote directory name.
	 *
	 * @return stdClass
	 */
	private function make_directory( $dir_name = '/WORDPRESS_BACKUPS' ) {

		$response = new stdClass();

		for ( $i = 1; $i < 4; $i++ ) {

			$api_response = wp_remote_get( $this->apiep . '/createfolder?path=' . $dir_name . '&name=' . trim( $dir_name, '/' ) . '&access_token=' . $this->authkey );
			if ( is_array( $api_response ) && ! is_wp_error( $api_response ) ) {
				$response_raw = wp_remote_retrieve_body( $api_response );
				if ( is_string( $response_raw ) && ! is_wp_error( $response_raw ) ) {
					$response_json = json_decode( $response_raw );
					if ( ! is_bool( $response_json ) ) {
						$response = $response_json;
						WP2pCloudDebugger::log( 'make_directory() - OK' );
						break;
					} else {
						WP2pCloudDebugger::log( 'make_directory() - failed to convert the response JSON to object! Will retry!' );
					}
				} else {
					WP2pCloudDebugger::log( 'make_directory() - no response body detected! Will retry!' );
				}
			} else {
				$error = '';
				if ( is_wp_error( $api_response ) ) {
					$error = $api_response->get_error_message();
				}
				WP2pCloudDebugger::log( 'make_directory() - api call failed ! [ ' . $error . ' ] Will retry!' );
			}

			sleep( 5 * $i );
		}

		return $response;
	}

	/**
	 * Get Upload directory ID
	 *
	 * @return int
	 */
	private function get_upload_dir_id() {
		$error = '';

		$response = new stdClass();

		$folder_id = 0;
		for ( $i = 1; $i < 4; $i++ ) {

			$api_response = wp_remote_get( $this->apiep . '/listfolder?path=/' . PCLOUD_BACKUP_DIR . '&access_token=' . $this->authkey );
			if ( is_array( $api_response ) && ! is_wp_error( $api_response ) ) {
				$response_raw = wp_remote_retrieve_body( $api_response );
				if ( is_string( $response_raw ) && ! is_wp_error( $response_raw ) ) {
					$response_json = json_decode( $response_raw );
					if ( ! is_bool( $response_json ) ) {
						$response = $response_json;
					} else {
						WP2pCloudDebugger::log( 'get_upload_dir_id() - failed to convert the response JSON to object!' );
					}
				} else {
					WP2pCloudDebugger::log( 'get_upload_dir_id() - no response body detected!' );
				}
			} else {

				if ( is_wp_error( $api_response ) ) {
					$error .= $api_response->get_error_message();
				}
				WP2pCloudDebugger::log( 'get_upload_dir_id() - api call failed ! [ ' . $error . ' ]' );
			}

			if ( ! property_exists( $response, 'result' ) && 2005 === $response->result ) {

				$folders = explode( '/', PCLOUD_BACKUP_DIR );
				WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='backup_in_fld'>Backup will be in folder:</span> " . PCLOUD_BACKUP_DIR );

				self::make_directory( '/' . $folders[0] );
				$res       = self::make_directory( '/' . $folders[0] . '/' . $folders[1] );
				$folder_id = $res->metadata->folderid;

			} else {

				if ( ! property_exists( $response, 'metadata' ) ) {
					WP2pCloudDebugger::log( 'get_upload_dir_id() - response from the API does not contain the needed info!' );
				} else {
					$folder_id = $response->metadata->folderid;
				}
			}

			if ( 0 < $folder_id ) {
				break;
			}

			sleep( 5 * $i );
		}

		if ( 0 === $folder_id ) {

			WP2pCloudDebugger::log( 'get_upload_dir_id() - api call failed ! [ ' . $error . ' ]' );

			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='invalid_resp_from_server'>Invalid response from the server:</span> " . $error . "\n" );
			WP2pCloudFuncs::set_operation( array() );
			die();
		}

		return $folder_id;
	}

	/**
	 * Chunked upload procedure
	 *
	 * @param string $path File path to be backed-up.
	 * @param int    $folder_id pCloud Folder ID.
	 * @param int    $upload_id pCloud Upload ID.
	 * @param int    $uploadoffset Upload offset.
	 * @param int    $num_failures Number of failures, will increase the wait time before the next try.
	 *
	 * @return int
	 */
	public function upload_chunk( $path, $folder_id = 0, $upload_id = 0, $uploadoffset = 0, $num_failures = 0 ) {

		$filesize = abs( filesize( $path ) );

		$this->set_chunk_size( $filesize );

		if ( ! file_exists( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='invalid_file_provided'>Invalid file provided!</span>" );
			WP2pCloudDebugger::log( 'upload_chunk() - Invalid file provided!' );
			return $uploadoffset + $this->part_size;
		}

		if ( $uploadoffset > $filesize ) {
			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='wp_all_done'>All Done!</span>" );
			WP2pCloudDebugger::log( 'upload_chunk() - All Done!' );
			return $uploadoffset;
		}

		$params = array(
			'uploadid'     => $upload_id,
			'uploadoffset' => $uploadoffset,
		);

		// Complicated file operations, currently not supported by: WP_Filesystem.
		$file = fopen( $path, 'r' ); // phpcs:ignore

		if ( $uploadoffset > 0 ) {
			fseek( $file, $uploadoffset ); // phpcs:ignore
		}
		$content = fread( $file, $this->part_size ); // phpcs:ignore
		try {
			if ( ! empty( $content ) ) {
				try {
					$this->write( $content, $params );

					$uploadoffset += $this->part_size;

				} catch ( Exception $e ) {

					$retry_in = $num_failures * 2;
					if ( $retry_in > 120 ) {
						$retry_in = 60;
					}

					$dbg_msg = $e->getMessage();

					WP2pCloudLogger::info( 'Upload failed with message: ' . $dbg_msg . ' will retry in: ' . $retry_in . ' sec.' );
					WP2pCloudDebugger::log( 'Upload failed with message: ' . $dbg_msg . ' will retry in: ' . $retry_in . ' sec.' );
					sleep( $retry_in );

				}
			}
			fclose( $file ); // phpcs:ignore

			if ( $uploadoffset > $filesize ) {

				$path     = str_replace( array( '\\' ), DIRECTORY_SEPARATOR, $path );
				$parts    = explode( DIRECTORY_SEPARATOR, $path );
				$filename = end( $parts );
				$this->save( $upload_id, $filename, $folder_id );

				$this->clear_all_tmp_files();

				WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='wp_done'>Done!</span>" );
				WP2pCloudDebugger::log( 'upload_chunk() -> DONE' );
			}
		} catch ( Exception $e ) {
			fclose( $file ); // phpcs:ignore
		}

		return $uploadoffset;
	}


	/**
	 * Upload procedure
	 *
	 * @param string $path File path to be backed-up.
	 * @param int    $folder_id Folder ID.
	 * @param int    $upload_id Upload ID.
	 * @param int    $uploadoffset Upload Offset.
	 *
	 * @return int
	 * @throws Exception Standart Exception can be thrown.
	 */
	public function upload( $path, $folder_id = 0, $upload_id = 0, $uploadoffset = 0 ) {
		if ( ! file_exists( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {

			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='invalid_file_provided'>Invalid file provided!</span>" );
			WP2pCloudDebugger::log( 'upload() -> Invalid file provided!' );
			return $uploadoffset + $this->part_size;

		} else {
			$filesize = abs( filesize( $path ) );

			$this->set_chunk_size( $filesize );
		}

		if ( $uploadoffset > $filesize ) {

			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='wp_all_done'>All Done!</span>" );
			WP2pCloudDebugger::log( 'upload() -> All Done!' );

			return $uploadoffset;
		}

		$params = array(
			'uploadid'     => $upload_id,
			'uploadoffset' => $uploadoffset,
		);

		$num_failures = 0;

		$file = fopen( $path, 'r' ); // phpcs:ignore
		while ( ! feof( $file ) ) {
			$content = fread( $file, $this->part_size ); // phpcs:ignore
			do {
				try {

					if ( PCLOUD_DEBUG ) {
						WP2pCloudLogger::info( 'Upl: prep2write !' );
						WP2pCloudDebugger::log( 'upload() -> prep2write' );
					}

					$this->write( $content, $params );

					if ( PCLOUD_DEBUG ) {
						WP2pCloudLogger::info( 'Upl: wrote done !' );
						WP2pCloudDebugger::log( 'upload() -> wrote done !' );
					}

					$params['uploadoffset'] += $this->part_size;
					$uploadoffset           += $this->part_size;

					if ( PCLOUD_DEBUG ) {
						WP2pCloudLogger::info( 'Upl: chunk ++ ->' . $uploadoffset );
						WP2pCloudDebugger::log( 'upload() -> chunk ++' );
					}

					$num_failures = 0;
					continue 2;

				} catch ( Exception $e ) {

					$dbg_ex = $e->getMessage();

					WP2pCloudLogger::info( 'ERR: ' . $dbg_ex . ' [id: ' . $upload_id . ' | offset: ' . $uploadoffset );
					WP2pCloudDebugger::log( 'upload() -> Exception: ' . $dbg_ex );

					if ( $num_failures > 5 ) {
						return $uploadoffset;
					}

					$num_failures ++;

					sleep( 3 );
				}
			} while ( $num_failures < 10 );
		}

		fclose( $file ); // phpcs:ignore

		if ( $uploadoffset >= $filesize ) {

			$path     = str_replace( array( '\\' ), DIRECTORY_SEPARATOR, $path );
			$parts    = explode( DIRECTORY_SEPARATOR, $path );
			$filename = end( $parts );

			$this->save( $upload_id, $filename, $folder_id );

			$this->clear_all_tmp_files();

			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='wp_done'>Done!</span>" );
			WP2pCloudDebugger::log( 'All done, saving !' . time() );
		}

		return $uploadoffset;
	}

	/**
	 * Prepare to initiate Upload process
	 *
	 * @return stdClass
	 * @throws Exception Standart Exception can be thrown.
	 */
	private function create_upload() {

		$response = new stdClass();

		for ( $i = 1; $i < 4; $i++ ) {

			$api_response = wp_remote_get( $this->apiep . '/upload_create?access_token=' . $this->authkey );
			if ( is_array( $api_response ) && ! is_wp_error( $api_response ) ) {
				$response_raw = wp_remote_retrieve_body( $api_response );
				if ( is_string( $response_raw ) && ! is_wp_error( $response_raw ) ) {
					$response_json = json_decode( $response_raw );
					if ( ! is_bool( $response_json ) ) {
						$response = $response_json;
						WP2pCloudDebugger::log( 'create_upload() - OK' );
						break;
					} else {
						WP2pCloudDebugger::log( 'create_upload() - failed to convert the response JSON to object! Will retry!' );
					}
				} else {
					WP2pCloudDebugger::log( 'create_upload() - no response body detected! Will retry!' );
				}
			} else {
				$error = '';
				if ( is_wp_error( $api_response ) ) {
					$error = $api_response->get_error_message();
				}
				WP2pCloudDebugger::log( 'create_upload() - api call failed ! [ ' . $error . ' ]! Will retry!' );
			}

			sleep( 5 * $i );
		}

		return $response;
	}

	/**
	 * After successfull upload - we need to call "save" procedure.
	 *
	 * @param int    $upload_id pCloud Upload ID.
	 * @param string $name File name to save.
	 * @param int    $folder_id pCloud Folder ID.
	 *
	 * @return void
	 * @throws Exception Standart Exception can be thrown.
	 */
	private function save( $upload_id, $name, $folder_id ) {

		$get_params = array(
			'uploadid'     => intval( $upload_id ),
			'name'         => rawurlencode( $name ),
			'folderid'     => intval( $folder_id ),
			'access_token' => rawurlencode( $this->authkey ),
		);

		$api_response = wp_remote_get( $this->apiep . '/upload_save?' . http_build_query( $get_params ) );
		if ( is_array( $api_response ) && ! is_wp_error( $api_response ) ) {
			$response_raw = wp_remote_retrieve_body( $api_response );
			if ( is_string( $response_raw ) && ! is_wp_error( $response_raw ) ) {
				if ( PCLOUD_DEBUG ) {
					WP2pCloudLogger::info( 'File remotelly saved !' );
				}
				WP2pCloudDebugger::log( 'save() - File remotelly saved ! [ uplid: ' . $upload_id . ', name: ' . $name . ', fldid: ' . $folder_id . ' ]' );
			} else {
				WP2pCloudDebugger::log( 'save() - no response body detected!' );
			}
		} else {
			$error = '';
			if ( is_wp_error( $api_response ) ) {
				$error = $api_response->get_error_message();
			}
			WP2pCloudDebugger::log( 'save() - api call failed ! [ ' . $error . ' ]' );
		}
	}

	/**
	 * Upload - write content chunk
	 *
	 * @param string     $content String content to be writen.
	 * @param array|null $get_params Additinal request params.
	 *
	 * @return void
	 * @throws Exception Standart Exception can be thrown.
	 */
	private function write( $content, $get_params ) {

		$err_message = 'failed to write to upload!';

		$get_params['access_token'] = $this->authkey;

		$args = array(
			'method'      => 'PUT',
			'timeout'     => 60,
			'redirection' => 5,
			'blocking'    => true,
			'headers'     => array(),
			'body'        => $content,
		);

		$api_response = wp_remote_request( $this->apiep . '/upload_write?' . http_build_query( $get_params ), $args );
		if ( is_array( $api_response ) && ! is_wp_error( $api_response ) ) {
			$response_body = wp_remote_retrieve_body( $api_response );
			$response_json = json_decode( $response_body, true );
			if ( ! is_bool( $response_json ) ) {
				if ( is_array( $response_json ) && isset( $response_json['result'] ) ) {
					if ( 0 === intval( $response_json['result'] ) ) {
						return;
					}
					if ( isset( $response_json['error'] ) && is_string( $response_json['error'] ) ) {
						$err_message = trim( $response_json['error'] );
					} else {
						WP2pCloudDebugger::log( 'write() - unexpected msg returned: ' . wp_json_encode( $response_json ) );
					}
				}
			} else {
				$err_message = 'write error json decode message:' . json_last_error_msg();
			}
		} else {
			if ( is_wp_error( $api_response ) ) {
				$err_message = $api_response->get_error_message();
			}
		}

		WP2pCloudDebugger::log( 'write() - api call failed ! [ ' . $err_message . ' ]' );

		throw new Exception( $err_message );
	}

	/**
	 * Validate the ZIP archive.
	 *
	 * @param string $file Filename to test.
	 *
	 * @return void
	 * @throws Exception Throws exception if issue is detected.
	 */
	private function validate_zip_archive( $file ) {

		$zip          = new ZipArchive();
		$open_archive = $zip->open( $file, ZIPARCHIVE::CHECKCONS );

		if ( is_bool( $open_archive ) && ! $open_archive ) {

			if ( ! is_null( $zip ) ) {
				$zip->close();
			}
			$zip = null;

			throw new Exception( 'error opening zip for validation!' );

		} elseif ( is_bool( $open_archive ) ) {

			if ( ! is_null( $zip ) ) {
				$zip->close();
			}
			$zip = null;
		}

		switch ( $open_archive ) {

			case ZipArchive::ER_MULTIDISK:
			case ZipArchive::ER_OK:
				break;

			case ZipArchive::ER_NOZIP:
				throw new Exception( 'not a zip archive' );
			case ZipArchive::ER_INCONS:
				throw new Exception( 'zip archive inconsistent' );
			case ZipArchive::ER_CRC:
				throw new Exception( 'checksum failed' );
			case ZipArchive::ER_INTERNAL:
				throw new Exception( 'internal error' );
			case ZipArchive::ER_EOF:
				throw new Exception( 'premature EOF' );
			case ZipArchive::ER_CHANGED:
				throw new Exception( 'entry has been changed' );
			case ZipArchive::ER_MEMORY:
				throw new Exception( 'memory allocation failure' );
			case ZipArchive::ER_ZLIB:
				throw new Exception( 'zlib error' );
			case ZipArchive::ER_TMPOPEN:
				throw new Exception( 'failure to create temporary file.' );
			case ZipArchive::ER_OPEN:
				throw new Exception( 'can\'t open file' );
			case ZipArchive::ER_SEEK:
				throw new Exception( 'seek error' );
			case ZipArchive::ER_NOENT:
				throw new Exception( 'ZIP file not found!' );

			default:
				throw new Exception( 'unknown error occured: ' . $open_archive );
		}

		if ( ! is_null( $zip ) ) {
			$zip->close();
		}
		$zip = null;
	}

	/**
	 * Set chunk size, based on the archive size.
	 *
	 * @param int $filesize ZIP Archive file.
	 * @return void
	 */
	public function set_chunk_size( $filesize ) {

		if ( ! is_numeric( $filesize ) ) {
			return;
		}

		if ( $filesize > ( 100 * 1000 * 1000 ) ) { // If Archive size is higher than 100MB.
			$this->part_size = 10 * 1000 * 1000;
		}
	}
}
