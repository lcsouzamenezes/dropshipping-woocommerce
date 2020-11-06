<?php
/**
 * Class for handle Async Requests.
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

if ( ! class_exists( 'WP_Async_Request', false ) ) {
	include_once plugin_dir_path( __FILE__ ) . 'lib/wp-background-processing/wp-async-request.php';
}

if( class_exists( 'WP_Async_Request', false ) ):

/*
 * Knawat_Dropshipping_WC_Async_Request Class
 */
class Knawat_Dropshipping_WC_Async_Request extends WP_Async_Request {

	/**
	 * @var string
	 */
	protected $action = 'kdropship_async';

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	protected function handle() {
		if( empty( $_POST ) ){
			return false;
		}
		if( isset( $_POST['operation'] ) && sanitize_text_field( $_POST['operation'] ) == 'pull_order' ){
			$knawat_order_id = isset( $_POST['knawat_order_id'] ) ? sanitize_text_field( $_POST['knawat_order_id'] ) : '';
			$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';
			if( $knawat_order_id != '' ){
				global $knawat_dropshipwc;
				$pulled = $knawat_dropshipwc->mp_orders->knawat_pull_knawat_order_information( $knawat_order_id, $order_id );
			}
		} elseif( isset( $_POST['operation'] ) && sanitize_text_field( $_POST['operation'] ) == 'delete_product' ){
			$delete_sku = isset( $_POST['delete_sku'] ) ? sanitize_text_field( $_POST['delete_sku'] ) : '';
			if( $delete_sku != '' ){
				global $knawat_dropshipwc;
				$knawat_dropshipwc->common->knawat_delete_mp_product_by_sku( $delete_sku );
			}
		} elseif( isset( $_POST['operation'] ) && sanitize_text_field( $_POST['operation'] ) == 'push_order' ){
			$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';
			if( $order_id != '' ){
				global $knawat_dropshipwc;
				sleep(5); // Wait for update order by other "woocommerce_update_order" calls
				$knawat_dropshipwc->mp_orders->knawatds_order_created_updated( $order_id );
			}
			return true;
		}

		// Update Product by SKU
		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( $_POST['sku'] ) : '';
		$force_update = isset( $_POST['force_update'] ) ? $_POST['force_update'] : false;
		if( $sku != '' ){
			global $knawat_dropshipwc;
			$updated = $knawat_dropshipwc->common->knawat_dropshipwc_import_product_by_sku( $sku, $force_update );
		}
	}
}

$knawatds_async = new Knawat_Dropshipping_WC_Async_Request();

endif;
