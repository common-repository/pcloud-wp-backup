<?php
/**
 * WP2pCloudDBBackUp class
 *
 * @file class-wp2pclouddbbackup.php
 * @package pcloud_wp_backup
 */

/**
 * Class WP2pCloudDBBackUp
 */
class WP2pCloudDBBackUp {

	/**
	 * Save file location
	 *
	 * @var string $save_file
	 */
	private $save_file;

	/**
	 * Class constructor
	 */
	public function __construct() {

		if ( PCLOUD_DEBUG && ! defined( 'WP_DEBUG_DISPLAY' ) ) {
			define( 'WP_DEBUG_DISPLAY', true );
		}

		$this->save_file = tempnam( sys_get_temp_dir(), 'sqlarchive' );
	}

	/**
	 * Initiates Database backup
	 *
	 * @throws Exception Throws standart Exception.
	 */
	public function start() {

		WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='start_db_backup'>Starting Database Backup</span>" );
		WP2pCloudDebugger::log( 'db_backup->start()' );

		$pl_dir_arr = dirname( __FILE__ );
		$pl_dir_arr = substr( $pl_dir_arr, 0, strrpos( $pl_dir_arr, '/' ) );

		require_once $pl_dir_arr . '/classes/class-pcl-type-adapter-mysql.php';
		require_once $pl_dir_arr . '/classes/class-pcl-mysqldump.php';

		$dump_settings = array(
			'exclude-tables'     => array(),
			'compress'           => Pcl_Mysqldump::NONE,
			'no-data'            => false,
			'add-drop-table'     => true,
			'single-transaction' => true,
			'lock-tables'        => false,
			'add-locks'          => false,
			'extended-insert'    => false,
			'disable-keys'       => true,
			'skip-triggers'      => false,
			'add-drop-trigger'   => true,
			'routines'           => true,
			'databases'          => false,
			'add-drop-database'  => false,
			'hex-blob'           => true,
			'no-create-info'     => false,
			'no-autocommit'      => false,
		);

		$dump = new Pcl_Mysqldump( 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, $dump_settings );

		try {
			$dump->start( $this->save_file );

			WP2pCloudDebugger::log( 'db_backup->start() - process succeeded!' );
			WP2pCloudLogger::info( "<span class='pcl_transl' data-i10nk='db_backup_finished'>Database Backup Finished</span>" );

		} catch ( Exception $e ) {
			$msg = $e->getMessage();

			WP2pCloudDebugger::log( 'db_backup->start() - Failed:' . $msg );
			WP2pCloudLogger::info( '<span>Plugin error: ' . $msg . '</span>' );
		}

		return $this->save_file;
	}
}
