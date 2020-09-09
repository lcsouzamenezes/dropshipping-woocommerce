<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Async_Request', false ) ) {
	include_once plugin_dir_path( __FILE__ ) . 'lib/wp-background-processing/wp-async-request.php';
}

if ( ! class_exists( 'WP_Background_Process', false ) ) {
	include_once plugin_dir_path( __FILE__ ) . 'lib/wp-background-processing/wp-background-process.php';
}

if( class_exists( 'WP_Background_Process', false ) ):

/*
 * WC_Product_Import_Background Class
 */
class Knawat_Dropshipping_WC_Background extends WP_Background_Process {
	/**
	 * @var string
	 */
	protected $action = 'kdropship_import';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {

		if( isset( $item['pull_operation'] ) && sanitize_text_field( $item['pull_operation'] ) == 'pull_order' ){
			global $knawat_dropshipwc;
			$pull_results = $knawat_dropshipwc->mp_orders->knawat_pull_knawat_orders( $item );
			if( !empty( $pull_results ) ){
				if( $pull_results['is_complete'] ){
					return false;
				}else{
					return $pull_results;
				}
			}
			return false;
		}

		$importer = new Knawat_Dropshipping_Woocommerce_Importer( 'full', $item );
		$results = $importer->import();
		$params = $importer->get_import_params();

		if( isset( $results['status'] ) && 'fail' === $results['status'] ){
			return false;
		}

		// Failed import data.
		$error_option = 'knawat_import_fail'.date('Ymd');
		$error_log = (array) get_option( $error_option, array() );
		$error_log  = array_merge( $error_log, $results['failed'] );
		if( !empty( $error_log ) ){
			update_option( $error_option, $error_log );
		}
		// Logs import data
		knawat_dropshipwc_logger( '[IMPORT_STATS_IMPORTER]'.print_r(
			array_filter($results, function($var) {
				return count($var) !== 0;
			}), true ), 'info' );

		$is_stop_import = get_transient('knawat_stop_import');
		if( $is_stop_import === 'product_import' ){
			delete_transient( 'knawat_stop_import' );
			$params['is_complete'] = true;
			$params['force_stopped'] = true;
		}

		if ( $params['is_complete'] ) {

			// Send success.
			$item = $params;

			$item['imported'] += count( $results['imported'] );
			$item['failed']   += count( $results['failed'] );
			$item['updated']  += count( $results['updated'] );

			if( !isset( $params['force_stopped'] ) ){
				// update option on import finish.
				update_option( 'knawat_full_import', 'done', false );
				update_option( 'knawat_last_imported', time(), false );
			}

			// Logs import data
			knawat_dropshipwc_logger( '[IMPORT_STATS_FINAL]'.print_r( $item, true ), 'info' );
			knawat_dropshipwc_logger( '[FAILED_IMPORTS]'.print_r( $error_log, true ) );

			// Return false to complete background import.
			return false;

		} else {

			$item = $params;
			if( $params['products_total'] == ( $params['product_index'] + 1 ) ){
				$item['page']  = $params['page'] + 1;
				$item['product_index']  = -1;
			}else{
				$item['page']  = $params['page'];
				$item['product_index']  = $params['product_index'];
			}

			$item['imported'] += count( $results['imported'] );
			$item['failed']   += count( $results['failed'] );
			$item['updated']  += count( $results['updated'] );

			// Return Update Item to importer
			return $item;
		}
		// Return false to complete background import.
		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
		// Show notice to user or perform some other arbitrary task...
	}

	/**
	 * Delete all import batches.
	 *
	 * @return Knawat_Dropshipping_WC_Background
	 */
	public function delete_all_batches() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // @codingStandardsIgnoreLine.

		return $this;
	}

	/**
	 * Kill process.
	 *
	 * Stop processing queue items, clear cronjob and delete all batches.
	 */
	public function kill_process() {
		if ( ! $this->is_queue_empty() ) {
			$this->delete_all_batches();
			wp_clear_scheduled_hook( $this->cron_hook_identifier );
		}
	}
}

$importer = new Knawat_Dropshipping_WC_Background();

endif;
