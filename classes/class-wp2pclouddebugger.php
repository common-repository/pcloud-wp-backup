<?php
/**
 * WP2pCloudDebugger class
 *
 * @file class-wp2pclouddebugger.php
 * @package pcloud_wp_backup
 */

/**
 * Class WP2pCloudDebugger
 */
class WP2pCloudDebugger {

	/**
	 * Generate new debug log
	 *
	 * @param string|null $initial_message Initial message ot be saved in the log.
	 *
	 * @return void
	 */
	public static function generate_new( $initial_message = '' ) {

		WP2pCloudFuncs::set_storred_val( PCLOUD_DBG_LOG, $initial_message );

	}

	/**
	 * Get last log content
	 *
	 * @param bool $as_json Choose the see the last log data as JSON or raw string.
	 *
	 * @return string
	 */
	public static function read_last_log( $as_json = true ) {
		$log = WP2pCloudFuncs::get_storred_val( PCLOUD_DBG_LOG );

		if ( strlen( $log ) > 50000 ) {
			$log = substr( $log, 20000 );
			WP2pCloudFuncs::set_storred_val( PCLOUD_DBG_LOG, $log );
		}

		if ( $as_json ) {
			return wp_json_encode( array( 'log' => $log ) );
		} else {
			return $log;
		}
	}

	/**
	 * Log any messages
	 *
	 * @param string $new_data String or HTML data to be added to the log.
	 *
	 * @return void
	 */
	public static function log( $new_data ) {

		$new_data = trim( strip_tags( $new_data, '<span><strong><br><em>' ) );
		if ( strlen( $new_data ) < 2 ) {
			return;
		}

		$current_data = WP2pCloudFuncs::get_storred_val( PCLOUD_DBG_LOG );
		$mem_usage    = WP2pCloudFuncs::memory_usage();

		if ( 'uploading' === $new_data ) {
			$current_data .= '.';
			if ( preg_match( '/\.{100}$/', $current_data ) ) {
				$current_data .= '<br/>';
			}
		} else {
			$current_data .= '<br/>' . gmdate( 'Y-m-d H:i:s' ) . ' [' . $mem_usage . '] - ' . $new_data;
		}

		WP2pCloudFuncs::set_storred_val( PCLOUD_DBG_LOG, $current_data );
	}
}
