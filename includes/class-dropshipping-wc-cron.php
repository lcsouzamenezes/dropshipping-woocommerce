<?php
/**
 * Class for handle Cron events
 *
 * @link       http://knawat.com
 * @since      2.0.0
 *
 * @package    Knawat_Dropshipping_Woocommerce
 * @subpackage Knawat_Dropshipping_Woocommerce/includes
 */
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Knawat_Dropshipping_WC_Cron Class
 */
class Knawat_Dropshipping_WC_Cron {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'knawat_cron_job_custom_recurrence' ) );
		add_action( 'knawat_dropshipwc_run_pull_orders', array( $this, 'knawat_run_pull_orders' ) );
	}

	/**
	 * Create Custom Cron Recurrences
	 *
	 * @access public
	 * @param  array $schedules Existing cron schedules
	 * @return array $schedules Altered cron schedules
	 */
	public function knawat_cron_job_custom_recurrence( $schedules ) {
		$interval                            = get_option( 'knawat_order_pull_cron_interval', 6 * 60 * 60 );
		$schedules['knawat_order_pull_cron'] = array(
			'display'  => __( 'Knawat Order Sync Interval', 'dropshipping-woocommerce' ),
			'interval' => $interval,
		);

		return $schedules;
	}

	/**
	 * Change Pull Orders Cron interval
	 *
	 * @access public
	 * @param  int $interval Cron interval value in seconds.
	 * @return void
	 */
	public function knawat_update_pull_order_cron_interval( $interval ) {
		if ( ! empty( $interval ) && is_numeric( $interval ) ) {
			if ( update_option( 'knawat_order_pull_cron_interval', sanitize_text_field( $interval ) ) ) {
				wp_clear_scheduled_hook( 'knawat_dropshipwc_run_pull_orders' );
				add_filter( 'cron_schedules', array( $this, 'knawat_cron_job_custom_recurrence' ) );
				wp_schedule_event( time(), 'knawat_order_pull_cron', 'knawat_dropshipwc_run_pull_orders' );
			}
		}
	}

	/**
	 * Run Scheduled cron for pull orders
	 *
	 * @access public
	 * @return void
	 */
	public function knawat_run_pull_orders() {
		$consumer_keys = knawat_dropshipwc_get_consumerkeys();
		if ( empty( $consumer_keys ) ) {
			return;
		}
		if ( ! class_exists( 'Knawat_Dropshipping_WC_Background', false ) ) {
			return;
		}
		// Validate access token
		do_action( 'knawat_dropshipwc_validate_access_token' );

		global $wpdb;
		$count_query = "SELECT count(option_id) as count FROM {$wpdb->options} WHERE option_name LIKE '%kdropship_import_batch_%' AND option_value LIKE '%pull_operation%' ORDER BY option_id ASC";
		if ( is_multisite() ) {
			$count_query = "SELECT count(meta_id) as count FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%kdropship_import_batch_%' AND meta_value LIKE '%pull_operation%' ORDER BY meta_id ASC";
		}
		$count = $wpdb->get_var( $count_query );
		if ( $count > 0 ) {
			// Another Order Synchronization is in process already
			return false;
		}

		$data          = array( 'pull_operation' => 'pull_order' );
		$data['page']  = 1;
		$data['limit'] = 10;
		$sync_process  = new Knawat_Dropshipping_WC_Background();
		$sync_process->push_to_queue( $data );
		$sync_process->save()->dispatch();
	}
}
