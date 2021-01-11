<?php
/**
 * Common functions class for Knawat Dropshipping Woocommerce.
 *
 * @link       http://knawat.com/
 * @since      1.0.0
 *
 * @package    Knawat_Dropshipping_Woocommerce
 * @subpackage Knawat_Dropshipping_Woocommerce/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Knawat_Dropshipping_Woocommerce_Common {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		// Do anything Here.
		add_action( 'knawat_dropshipwc_run_product_import', array( $this, 'knawat_dropshipwc_backgorund_product_importer' ) );
		add_action( 'admin_init', array( $this, 'handle_knawat_settings_submit' ), 99 );
		add_action( 'admin_post_resyncs_knawat_products', array( $this, 'resync_knawat_products' ), 99 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'knawat_dropshipwc_add_to_cart' ), 10, 2 );
		add_action( 'woocommerce_before_single_product', array( $this, 'knawat_dropshipwc_before_single_product' ) );
		add_action( 'knawat_dropshipwc_validate_access_token', array( $this, 'validate_access_token' ) );
		add_action( 'admin_init', array( $this, 'maybe_display_access_token_warning' ) );
		add_action( 'admin_init', array( $this, 'display_knawat_persistent_notices' ) );
		add_action( 'wp_ajax_knawat_dismiss_admin_notice', array( $this, 'knawat_dismiss_admin_notice' ) );
		add_action( 'before_delete_post', array( $this, 'knawat_delete_product_on_mp' ) );
		add_action( 'wp_trash_post', array( $this, 'knawat_show_notice_for_delete' ) );
	}
	
	/**
	 * Check is WooCommerce Activate or not.
	 *
	 * @return     boolean
	 * @since    1.0.0
	 */
	public function knawat_dropshipwc_is_woocommerce_activated() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check is WooCommerce Activate or not.
	 *
	 * @return     boolean
	 * @since    1.0.0
	 */
	public function knawat_dropshipwc_is_woomulti_currency_activated() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if ( is_plugin_active( 'woocommerce-currency-switcher/index.php' ) || is_plugin_active( 'currency-switcher-woocommerce/currency-switcher-woocommerce.php' ) || is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) || is_plugin_active( 'woo-multi-currency/woo-multi-currency.php' ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check order contains knawat products or not.
	 *
	 * @return     boolean
	 * @since    1.0.0
	 */
	public function is_knawat_order( $order_id ) {
		if ( empty( $order_id ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		$items = $order->get_items();
		foreach ( $items as $item ) {
			if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
				$product_id   = $item->get_product_id();
				$dropshipping = get_post_meta( $product_id, 'dropshipping', true );
				if ( $dropshipping == 'knawat' ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Knawat products import, trigged by cronjob or import button
	 *
	 * @param array $args
	 *
	 * @return void
	 * @since    1.0.0
	 */
	public function knawat_dropshipwc_backgorund_product_importer( $args = array() ) {
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
		$count_query = "SELECT count(option_id) as count FROM {$wpdb->options} WHERE option_name LIKE '%kdropship_import_batch_%' AND option_value NOT LIKE '%pull_operation%' ORDER BY option_id ASC";
		if ( is_multisite() ) {
			$count_query = "SELECT count(meta_id) as count FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%kdropship_import_batch_%' AND meta_value NOT LIKE '%pull_operation%' ORDER BY meta_id ASC";
		}
		$count = $wpdb->get_var( $count_query );

		if ( $count > 0 ) {
			global $knawatdswc_errors;
			$knawatdswc_errors[] = __( 'Another product import is in process already.', 'dropshipping-woocommerce' );

			return false;
		}

		$product_batch_size = knawat_dropshipwc_get_product_batch_size();
		if ( empty( $product_batch_size ) || $product_batch_size < 0 || $product_batch_size > 1000 ) {
			$product_batch_size = 25;
		}
		$data           = array();
		$data['limit']  = $product_batch_size;
		$data           = wp_parse_args( $data, $args );
		$import_process = new Knawat_Dropshipping_WC_Background();
		$import_process->push_to_queue( $data );
		$import_process->save()->dispatch();
	}

	/**
	 * Process settings form and save settings.
	 *
	 * @since    2.0.0
	 */
	public function handle_knawat_settings_submit() {
		global $knawatdswc_success, $knawat_dropshipwc;
		if ( isset( $_POST['knawatds_action'] ) && $_POST['knawatds_action'] == 'knawatds_save_settings' ) {
			// Verify nonce.
			check_admin_referer( 'knawatds_setting_form_nonce_action', 'knawatds_setting_form_nonce' );

			$knawatds_options = isset( $_POST['knawat'] ) ? $_POST['knawat'] : array();
			$current_options  = knawat_dropshipwc_get_options();
			if ( isset( $knawatds_options['mp_consumer_key'] ) ) {
				$current_options['mp_consumer_key'] = sanitize_text_field( $knawatds_options['mp_consumer_key'] );
			}
			if ( isset( $knawatds_options['mp_consumer_secret'] ) ) {
				$current_options['mp_consumer_secret'] = sanitize_text_field( $knawatds_options['mp_consumer_secret'] );
			}
			if ( isset( $knawatds_options['product_batch'] ) && is_numeric( $knawatds_options['product_batch'] ) ) {
				$current_options['product_batch'] = sanitize_text_field( $knawatds_options['product_batch'] );
			}

			if ( isset( $knawatds_options['categorize_products'] ) ) {
				$current_options['categorize_products'] = sanitize_text_field( $knawatds_options['categorize_products']);    
			}else{
				$current_options['categorize_products'] = 'no';
			}

			if ( isset( $knawatds_options['dokan_seller'] ) && is_numeric( $knawatds_options['dokan_seller'] ) ) {
				$current_options['dokan_seller'] = sanitize_text_field( $knawatds_options['dokan_seller'] );
			}

			if ( isset( $_POST['order_pull_interval'] ) && is_numeric( $_POST['order_pull_interval'] ) ) {
				$knawat_dropshipwc->cron->knawat_update_pull_order_cron_interval( sanitize_text_field( $_POST['order_pull_interval'] ) );
			}

			knawat_dropshipwc_update_options( $current_options );

			// Validate access token on keys.
			do_action( 'knawat_dropshipwc_validate_access_token' );
			$knawatdswc_success[] = __( 'Settings has been saved successfully.', 'dropshipping-woocommerce' );
		}
	}

	/**
	 * Resynchronization products from knawat.com
	 *
	 * @since    2.7.0
	 */
	public function resync_knawat_products() {
		global $knawatdswc_success, $knawat_dropshipwc;
		$query_string = $_GET;
		if( wp_verify_nonce( sanitize_key( $query_string['product_sync'] ), 'knawat_product_sync_action') ){
			update_option( 'knawat_last_imported','1483300000000');
			do_action( 'knawat_dropshipwc_validate_access_token' );
			$redirect_url = esc_url_raw( add_query_arg( array( 'tab' => 'settings' ), admin_url('admin.php?page=knawat_dropship')));
			wp_safe_redirect(  $redirect_url );
			exit();
		}
	}

	/**
	 * Sync prduct with knawat.com during product add_to_cart
	 *
	 * @since 2.0.0
	 */
	public function knawat_dropshipwc_add_to_cart( $cart_item_key, $product_id ) {
		if ( empty( $product_id ) ) {
			return;
		}
		// Update product
		$this->knawat_dropshipwc_async_product_update_by_id( $product_id );
	}

	/**
	 * Sync prduct with knawat.com during custmer visit single product page
	 *
	 * @since 2.0.0
	 */
	public function knawat_dropshipwc_before_single_product() {
		$product_id = get_the_ID();
		if ( empty( $product_id ) ) {
			return;
		}
		// Update product
		$this->knawat_dropshipwc_async_product_update_by_id( $product_id );
	}

	/**
	 * Run Async call for Sync prduct with knawat.com by product_id
	 *
	 * @param $product_id            int  Product ID.
	 *
	 * @since 2.0.0
	 */
	public function knawat_dropshipwc_async_product_update_by_id( $product_id, $force_update = false ) {

		if ( empty( $product_id ) ) {
			return;
		}

		$product = new WC_Product( $product_id );
		$sku     = $product->get_sku();

		if ( empty( $sku ) ) {
			return;
		}

		if ( ! class_exists( 'Knawat_Dropshipping_WC_Async_Request', false ) ) {
			return;
		}

		// Async Product Update.
		$async_request = new Knawat_Dropshipping_WC_Async_Request();
		$data = array( 'sku' => $sku );
		if ($force_update) {
			$data['force_update'] = $force_update;
		}
		$async_request->data( $data );
		$async_request->dispatch();
	}

	/**
	 * Sync prduct with knawat.com by Sku
	 *
	 * @param $sku            string  Product SKU.
	 * @param $force_update boolean True if is force update.
	 *
	 * @return array Import status.
	 *
	 * @since 2.0.0
	 */
	function knawat_dropshipwc_import_product_by_sku( $sku, $force_update = false ) {
		if ( empty( $sku ) ) {
			return false;
		}
		$sku      = sanitize_text_field( $sku );
		$importer = new Knawat_Dropshipping_Woocommerce_Importer( 'single', array( 'sku' => $sku, 'limit' => 1, 'force_update' => $force_update ) );
		$import   = $importer->import();

		return $import;
	}

	/**
	 * Validate Access Token
	 *
	 *
	 * @access public
	 * @return string
	 */
	public function validate_access_token() {

		$current_options = knawat_dropshipwc_get_options();
		// Remove knawat_mp_access_channel transient for re-fetch token
		$mp_api = new Knawat_Dropshipping_Woocommerce_API();
		delete_transient( 'knawat_mp_access_channel' );
		$token = $mp_api->get_access_token();
		if ( ! empty( $token ) ) {
			$current_options['token_status'] = 'valid';
		} else {
			$current_options['token_status'] = 'invalid';
		}
		knawat_dropshipwc_update_options( $current_options );
	}

	/**
	 * Check if access token is not valid and display warning if token is not valid.
	 *
	 * @return     void
	 * @since    2.0.0
	 */
	public function maybe_display_access_token_warning() {
		$knawat_options = knawat_dropshipwc_get_options();
		$token_status   = isset( $knawat_options['token_status'] ) ? esc_attr( $knawat_options['token_status'] ) : '';
		if ( 'invalid' === $token_status ) {
			if ( ( isset( $_GET['page'] ) && 'knawat_dropship' === sanitize_text_field( $_GET['page'] ) ) && ( isset( $_GET['tab'] ) && 'settings' === sanitize_text_field( $_GET['tab'] ) ) ) {
				return;
			}
			global $knawat_dropshipwc, $knawatdswc_warnings;
			$knawatdswc_warnings[] = sprintf( '%s <a href="' . esc_url( add_query_arg( 'tab', 'settings', $knawat_dropshipwc->admin->adminpage_url ) ) . '" >%s</a>',
				__( 'Your connection with knawat.com has been disconnected. Please check and verify your knawat consumer keys from', 'dropshipping-woocommerce' ),
				__( '<strong>Knawat Dropshipping</strong> > <strong>Settings</strong>.', 'dropshipping-woocommerce' )
			);
		}
	}

	/**
	 * Dismiss Admin notice for Forever
	 *
	 * @return     boolean
	 * @since    1.0.0
	 */
	public function knawat_dismiss_admin_notice() {
		$notice_type = sanitize_text_field( $_POST['notice_type'] );
		if ( $notice_type != '' ) {
			$notice_type = sanitize_key( $notice_type );
			check_ajax_referer( 'kdropshipping_nonce', 'nonce' );
			update_option( $notice_type, 'dismissed' );
		}
		wp_die();
	}

	/**
	 * Dismiss Admin notice for Forever
	 *
	 * @return     boolean
	 * @since    1.0.0
	 */
	public function is_admin_notice_active( $notice_type ) {
		if ( empty( $notice_type ) ) {
			return false;
		}
		$notice_type = sanitize_key( $notice_type );
		$is_active   = get_option( $notice_type, false );
		if ( $is_active ) {
			return false;
		}

		return true;
	}

	/**
	 * Display persistent notices.
	 *
	 * @return     void
	 * @since    2.0.0
	 */
	public function display_knawat_persistent_notices( $remove = true ) {
		$persistent_notices = get_transient( 'knawat_persistent_notices' );
		if ( ! empty( $persistent_notices ) ) {
			global $knawatdswc_errors, $knawatdswc_success, $knawatdswc_warnings;
			if ( isset( $persistent_notices['errors'] ) && is_array( $persistent_notices['errors'] ) ) {
				$knawatdswc_errors = array_merge( $knawatdswc_errors, $persistent_notices['errors'] );
			}
			if ( isset( $persistent_notices['success'] ) && is_array( $persistent_notices['success'] ) ) {
				$knawatdswc_success = array_merge( $knawatdswc_success, $persistent_notices['success'] );
			}
			if ( isset( $persistent_notices['warnings'] ) && is_array( $persistent_notices['warnings'] ) ) {
				$knawatdswc_warnings = array_merge( $knawatdswc_warnings, $persistent_notices['warnings'] );
			}
		}
		delete_transient( 'knawat_persistent_notices' );
	}

	/**
	 * Async Call for delete MP product.
	 *
	 * @param int $post_id
	 */
	function knawat_delete_product_on_mp( $post_id ) {
		$post = get_post( $post_id );

		if ( $post->post_type == 'product' ) {
			$is_knawat = knawat_dropshipwc_is_knawat_product( $post_id );
			if ( ! $is_knawat ) {
				return;
			}

			$product = wc_get_product( $post_id );
			$sku     = $product->get_sku();
			if ( ! class_exists( 'Knawat_Dropshipping_WC_Async_Request', false ) || empty( $sku ) ) {
				return;
			}

			// Async Product Update.
			$data          = array( 'operation' => 'delete_product', 'delete_sku' => $sku );
			$async_request = new Knawat_Dropshipping_WC_Async_Request();
			$async_request->data( $data );
			$async_request->dispatch();
		}
	}

	/**
	 * Delete MP product by SKU.
	 *
	 * @param string $sku
	 */
	function knawat_delete_mp_product_by_sku( $sku ) {
		if ( ! class_exists( 'Knawat_Dropshipping_Woocommerce_API', false ) || empty( $sku ) ) {
			return;
		}

		// Call API for delete products.
		$mp_api     = new Knawat_Dropshipping_Woocommerce_API();
		$is_deleted = $mp_api->delete( 'catalog/products/' . sanitize_text_field( $sku ) );
		if ( isset( $is_deleted->product->status ) ) {
			if ( 'success' === $is_deleted->product->status ) {
				knawat_dropshipwc_logger( '[MP_PRODUCT_DELETED] SKU:' . $sku, 'info' );
			} else {
				if ( isset( $is_deleted->product->message ) ) {
					knawat_dropshipwc_logger( '[MP_PRODUCT_DELETE_FAIL] SKU:' . $sku . ' REASON:' . $is_deleted->product->message, 'error' );
				}
			}
		}
	}

	/**
	 * Display Delete notice during trash product
	 *
	 * @param int $post_id
	 */
	function knawat_show_notice_for_delete( $post_id ) {
		$post = get_post( $post_id );

		if ( $post->post_type == 'product' ) {
			$is_knawat = knawat_dropshipwc_is_knawat_product( $post_id );
			if ( $is_knawat ) {
				$messages             = array();
				$messages['warnings'] = array( esc_attr__( 'The product will be imported again from Knawat, if you don\'t want this product, then delete it permanently.', 'dropshipping-woocommerce' ) );
				knawat_set_notices( $messages );
			}
		}
	}


}

/*
 * Woocommerce WebHooks Utilities
 */
add_filter( 'http_request_args', function ( $args ) {
	$args['reject_unsafe_urls'] = false;

	return $args;
} );

/*
 * Store is contected to knawat.com or not
 */
function knawat_dropshipwc_is_connected() {
	global $wpdb, $knawat_dropshipwc;

	if ( $knawat_dropshipwc->is_woocommerce_activated() ) {
		$t_api_keys    = $wpdb->prefix . 'woocommerce_api_keys';
		$api_key_query = "SELECT COUNT( key_id ) as count FROM {$t_api_keys} WHERE `description` LIKE '%Knawat - API%'";
		$key_count     = $wpdb->get_var( $api_key_query );
		if ( $key_count > 0 ) {
			return true;
		}
	}

	return false;
}

/**
 * Get Defined DropShippers.
 *
 * @access public
 * @return array
 * @since 1.2.0
 */
function knawat_dropshipwc_get_dropshippers() {
	global $knawat_dropshipwc;
	$dropshippers = $knawat_dropshipwc->get_dropshippers();
	if ( empty( $dropshippers ) && ! is_array( $dropshippers ) ) {
		$dropshippers = array();
	}

	return $dropshippers;
}

/**
 * Get Knawat DropShipping Options.
 *
 * @access public
 * @return array
 * @since 2.0.0
 */
function knawat_dropshipwc_get_options( $key = '' ) {
	$knawat_options = get_option( KNAWAT_DROPWC_OPTIONS, array() );
	if ( $key != '' ) {
		$knawat_options = isset( $knawat_options[ $key ] ) ? $knawat_options[ $key ] : '';
	}

	return $knawat_options;
}

/**
 * Update Knawat DropShipping Options.
 *
 * @access public
 * @return array
 * @since 2.0.0
 */
function knawat_dropshipwc_update_options( $knawat_options ) {
	update_option( KNAWAT_DROPWC_OPTIONS, $knawat_options );
}

/**
 * Get Knawat MP Consumer Keys
 *
 * @return array Knawat MP Consumer Keys
 */
function knawat_dropshipwc_get_consumerkeys() {
	$consumer_keys  = array();
	$knawat_options = knawat_dropshipwc_get_options();
	if ( isset( $knawat_options['mp_consumer_key'] ) && ! empty( $knawat_options['mp_consumer_key'] ) ) {
		$consumer_keys['consumer_key'] = $knawat_options['mp_consumer_key'];
	}
	if ( isset( $knawat_options['mp_consumer_secret'] ) && ! empty( $knawat_options['mp_consumer_secret'] ) ) {
		$consumer_keys['consumer_secret'] = $knawat_options['mp_consumer_secret'];
	}

	return $consumer_keys;
}

/**
 * Get Product Batch Size.
 *
 * @return array Order statuses
 */
function knawat_dropshipwc_get_product_batch_size() {
	$product_batch  = 25;
	$knawat_options = knawat_dropshipwc_get_options();
	if ( isset( $knawat_options['product_batch'] ) && ! empty( $knawat_options['product_batch'] ) ) {
		$product_batch = $knawat_options['product_batch'];
	}

	return $product_batch;
}

/**
 * Get Knawat Compatible Active plugins
 *
 * @return array Knawat Compatible plugins with status.
 */
function knawat_dropshipwc_get_activated_plugins() {
	$active_plugins = array(
		'featured-image-by-url'         => false,
		'woocommerce-currency-switcher' => false,
		'qtranslate-xt'                 => false,
	);

	$blog_plugins = get_option( 'active_plugins', array() );
	$site_plugins = is_multisite() ? (array) maybe_unserialize( get_site_option( 'active_sitewide_plugins' ) ) : array();

	// Check if qTranslate XT is activated
	if ( in_array( 'qtranslate-xt/qtranslate.php', $blog_plugins ) || isset( $site_plugins['qtranslate-xt/qtranslate.php'] ) ) {
		$active_plugins['qtranslate-xt'] = true;
	}

	// Check if Featured image by URL is activated
	if ( in_array( 'featured-image-by-url/featured-image-by-url.php', $blog_plugins ) || isset( $site_plugins['featured-image-by-url/featured-image-by-url.php'] ) ) {
		$active_plugins['featured-image-by-url'] = true;
	}

	// Check if WooCommerce Currency Switcher is activated
	if ( in_array( 'woocommerce-currency-switcher/index.php', $blog_plugins ) || isset( $site_plugins['woocommerce-currency-switcher/index.php'] ) ) {
		$active_plugins['woocommerce-currency-switcher'] = true;
	}

	return $active_plugins;
}

/**
 * Get Total Number of Products with specific timestamp
 * 
 * @return int $products_count The total number of products
 */
function knawat_dropshipwc_get_products_count( $timestamp , $hideOutOfStock = true){
	// MP request to get total count of synced products with $timestamp
	$mp_api = new Knawat_Dropshipping_Woocommerce_API();
	if($hideOutOfStock){
		$data 	= $mp_api->get('catalog/products/count?lastUpdate='.$timestamp);
	}else{
		$data 	= $mp_api->get('catalog/products/count?lastUpdate='.$timestamp.'&hideOutOfStock=1');
	}
	$products_count = $data->total;
	return $products_count;
}


/**
 * Check if order is for knawat local DS.
 *
 * @param int $order_id The ID of the order
 *
 * @return string|bool
 */
function knawat_dropshipwc_is_order_local_ds( $order_id ) {
	if ( empty( $order_id ) ) {
		return false;
	}
	$knawat_order_ds = get_post_meta( $order_id, '_knawat_order_ds', true );
	$dropshippers    = knawat_dropshipwc_get_dropshippers();
	if ( ! empty( $knawat_order_ds ) && isset( $dropshippers[ $knawat_order_ds ] ) ) {
		return true;
	}

	return false;
}

/**
 * Check if Access token is valid or not.
 *
 * @return bool
 */
function knawat_dropshipwc_is_access_token_valid() {
	$knawat_options = knawat_dropshipwc_get_options();
	$token_status   = isset( $knawat_options['token_status'] ) ? esc_attr( $knawat_options['token_status'] ) : '';
	if ( 'valid' === $token_status ) {
		return true;
	}

	return false;
}

/**
 * Log Errors and warning using WooCommerce Logger
 *
 * @return void
 */
function knawat_dropshipwc_logger( $message, $type = 'error' ) {
	if ( function_exists( 'wc_get_logger' ) && $message != '' ) {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'dropshipping-woocommerce' );
		$logger->log( $type, $message, $context );
	}
}

/**
 * Knawat Set persistent notices (which is used to show after page redirect).
 *
 * @return void
 */
function knawat_set_notices( $messages ) {
	if ( ! empty( $messages ) ) {
		set_transient( 'knawat_persistent_notices', $messages, 5 * MINUTE_IN_SECONDS );
	}
}

/**
 * Check if its knawat product or not
 *
 * @param int $product_id Product ID
 *
 * @return boolean
 */
function knawat_dropshipwc_is_knawat_product( $product_id ) {
	$dropshipping = get_post_meta( $product_id, 'dropshipping', true );
	if ( $dropshipping == 'knawat' ) {
		return true;
	}

	return false;
}

/**
 * Check if dokan is active or not.
 *
 * @return boolean
 */
function knawat_dropshipwc_is_dokan_active() {
	if ( class_exists( 'WeDevs_Dokan', false ) ) {
		return true;
	}

	return false;
}

/**
 * Get Batch of inprocess product import.
 *
 * @return array $batches.
 */
function knawat_dropshipwc_get_inprocess_import() {
	global $wpdb;
	$batch_query = "SELECT * FROM {$wpdb->options} WHERE option_name LIKE '%kdropship_import_batch_%' AND option_value NOT LIKE '%pull_operation%' ORDER BY option_id ASC LIMIT 1";
	if ( is_multisite() ) {
		$batch_query = "SELECT * FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%kdropship_import_batch_%' AND meta_value NOT LIKE '%pull_operation%' ORDER BY meta_id ASC LIMIT 1";
	}
	$batches = $wpdb->get_results( $batch_query );

	return $batches;
}

/**
 * Delete Deprected Webhooks.
 *
 * @return void.
 */
function knawat_dropshipwc_delete_deprecated_webhooks() {
	global $wpdb;
	$query   = "SELECT webhook_id FROM {$wpdb->prefix}wc_webhooks WHERE topic IN ('order.knawatcreated', 'order.knawatupdated', 'order.knawatdeleted', 'order.knawatrestored')";
	$results = $wpdb->get_results( $query ); // WPCS: cache ok, DB call ok, unprepared SQL ok.
	$ids     = wp_list_pluck( $results, 'webhook_id' );
	if ( class_exists( 'WC_Webhook', false ) && ! empty( $ids ) ) {
		foreach ( $ids as $webhook_id ) {
			$webhook = new WC_Webhook( (int) $webhook_id );
			$webhook->delete( true );
		}
	}
}

/**
 * Delete Deprected API Keys.
 *
 * @return void.
 */
function knawat_dropshipwc_delete_deprecated_api_keys() {
	global $wpdb;
	$delete_query = "DELETE FROM {$wpdb->prefix}woocommerce_api_keys WHERE description LIKE 'Knawat - API %'";
	$wpdb->query( $delete_query );
}
