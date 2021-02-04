<?php
/**
 * Class for add new custom Webhook topics in WooCommerce
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

class Knawat_Dropshipping_Woocommerce_Webhook {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		add_filter( 'woocommerce_webhook_topic_hooks', array( $this, 'add_new_topic_hooks' ) );
		add_filter( 'woocommerce_valid_webhook_events', array( $this, 'add_new_webhook_events' ) );
		add_filter( 'woocommerce_webhook_topics', array( $this, 'add_new_webhook_topics' ) );

		// Handles Knawat Order Created & Updated Topic.
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'knawat_order_created_updated_callback' ) );
		add_action( 'woocommerce_new_order', array( $this, 'knawat_order_created_updated_callback' ) );
		add_action( 'woocommerce_update_order', array( $this, 'knawat_order_created_updated_callback' ) );

		add_action( 'woocommerce_checkout_order_processed', array( $this, 'knawat_order_created_callback' ), 10, 3 );

		// Handles Knawat Order Delete & Restore.
		add_action( 'wp_trash_post', array( $this, 'knawat_order_deleted_callback' ) );
		add_action( 'untrashed_post', array( $this, 'knawat_order_restored_callback' ) );
	}

	/**
	 * Adds new webhook topic hooks.
	 *
	 * @param  Array $topic_hooks  Existing topic hooks.
	 *
	 * @return Array
	 */
	function add_new_topic_hooks( $topic_hooks ) {
		// Array that has the topic as resource.event with arrays of actions that call that topic.
		$dropshippers      = knawat_dropshipwc_get_dropshippers();
		$dropshipper_hooks = array();
		foreach ( $dropshippers as $key => $value ) {
			if ( 'default' === $key ) {
				continue;
			}

			$dropshipper_temp_hooks = array(
				'order.' . $key . '_knawatcreated'  => array(
					$key . '_knawat_order_created',
				),
				'order.' . $key . '_knawatupdated'  => array(
					$key . '_knawat_order_updated',
				),
				'order.' . $key . '_knawatdeleted'  => array(
					$key . '_knawat_order_deleted',
				),
				'order.' . $key . '_knawatrestored' => array(
					$key . '_knawat_order_restored',
				),
			);
			$dropshipper_hooks      = array_merge( $dropshipper_hooks, $dropshipper_temp_hooks );
		}

		return array_merge( $topic_hooks, $dropshipper_hooks );
	}

	/**
	 * Adds new events for topic resources.
	 *
	 * @param  Array $events   Existing Events.
	 *
	 * @return Array
	 */
	function add_new_webhook_events( $events ) {
		// new resource
		$dropshippers       = knawat_dropshipwc_get_dropshippers();
		$dropshipper_events = array();
		foreach ( $dropshippers as $key => $value ) {
			if ( 'default' === $key ) {
				continue;
			}
			$dropshipper_temp_events = array( $key . '_knawatcreated', $key . '_knawatupdated', $key . '_knawatdeleted', $key . '_knawatrestored' );
			$dropshipper_events      = array_merge( $dropshipper_events, $dropshipper_temp_events );
		}
		return array_merge( $events, $dropshipper_events );
	}

	/**
	 * add_new_webhook_topics adds the new webhook to the dropdown list on the Webhook page.
	 *
	 * @param array $topics Array of topics with the i18n proper name.
	 *
	 * @return Array
	 */
	function add_new_webhook_topics( $topics ) {
		// New topic array to add to the list, must match hooks being created.
		$dropshippers       = knawat_dropshipwc_get_dropshippers();
		$dropshipper_topics = array();
		foreach ( $dropshippers as $key => $value ) {
			if ( 'default' === $key ) {
				continue; }
			$dropshipper_temp_topics = array(
				'order.' . $key . '_knawatcreated'  => sprintf( __( 'Knawat Order created (%s)', 'dropshipping-woocommerce' ), $value['name'] ),
				'order.' . $key . '_knawatupdated'  => sprintf( __( 'Knawat Order updated (%s)', 'dropshipping-woocommerce' ), $value['name'] ),
				'order.' . $key . '_knawatdeleted'  => sprintf( __( 'Knawat Order deleted (%s)', 'dropshipping-woocommerce' ), $value['name'] ),
				'order.' . $key . '_knawatrestored' => sprintf( __( 'Knawat Order restored (%s)', 'dropshipping-woocommerce' ), $value['name'] ),
			);
			$dropshipper_topics      = array_merge( $dropshipper_topics, $dropshipper_temp_topics );
		}
		return array_merge( $topics, $dropshipper_topics );
	}


	/**
	 * knawat_order_created_updated_callback will run on order create/update.
	 * if it is knawat local DS order then related action get fired.
	 *
	 * @param  int $order_id    The ID of the order that was just created.
	 *
	 * @return null
	 */
	function knawat_order_created_updated_callback( $order_id ) {

		$current_action = current_action();
		if ( $current_action == 'woocommerce_process_shop_order_meta' ) {
			// the `woocommerce_process_shop_*` and `woocommerce_process_product_*` hooks
			// fire for create and update of products and orders, so check the post
			// creation date to determine the actual event
			$resource = get_post( absint( $order_id ) );

			// Drafts don't have post_date_gmt so calculate it here
			$gmt_date = get_gmt_from_date( $resource->post_date );

			// a resource is considered created when the hook is executed within 10 seconds of the post creation date
			$resource_created = ( ( time() - 10 ) <= strtotime( $gmt_date ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) ) {
			$is_knawat = get_post_meta( $order_id, '_knawat_order', true );
			if ( 1 == $is_knawat ) {
				$knawat_ds = $this->knawat_is_order_local_ds( $order_id );
				if ( $knawat_ds != '' ) {
					if ( $current_action == 'woocommerce_process_shop_order_meta' && $resource_created ) {
						do_action( $knawat_ds . '_knawat_order_created', $order_id );

					} elseif ( $current_action == 'woocommerce_update_order' ) {
						do_action( $knawat_ds . '_knawat_order_updated', $order_id );

					} elseif ( $current_action == 'woocommerce_new_order' ) {
						do_action( $knawat_ds . '_knawat_order_created', $order_id );

					} else {
						do_action( $knawat_ds . '_knawat_order_updated', $order_id );
					}
				}
			}
		}
	}

	/**
	 * knawat_order_created_callback will run on order create.
	 * if it is knawat local DS order then related action get fired.
	 *
	 * @param  int $order_id    The ID of the order that was just created.
	 *
	 * @return null
	 */
	function knawat_order_created_callback( $order_id, $posted_data, $order ) {

		$post_type = get_post_type( $order_id );
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) ) {
			$is_knawat = get_post_meta( $order_id, '_knawat_order', true );
			if ( 1 == $is_knawat ) {
				$knawat_ds = $this->knawat_is_order_local_ds( $order_id );
				if ( $knawat_ds != '' ) {
					do_action( $knawat_ds . '_knawat_order_created', $order_id, $posted_data, $order );
				}
			}
		}
	}

	/**
	 * knawat_order_deleted_callback will run on order delete.
	 * if it is knawat local DS order then related action get fired.
	 *
	 * @param  int $order_id    The ID of the order that was just deleted.
	 *
	 * @return null
	 */
	function knawat_order_deleted_callback( $order_id ) {

		$post_type = get_post_type( $order_id );
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) ) {
			$is_knawat = get_post_meta( $order_id, '_knawat_order', true );
			if ( 1 == $is_knawat ) {
				$knawat_ds = $this->knawat_is_order_local_ds( $order_id );
				if ( $knawat_ds != '' ) {
					do_action( $knawat_ds . '_knawat_order_deleted', $order_id );
				}
			}
		}
	}

	/**
	 * knawat_order_restored_callback will run on order restore.
	 * if it is knawat local DS order then related action get fired.
	 *
	 * @param  int $order_id    The ID of the order that was just restored.
	 *
	 * @return null
	 */
	function knawat_order_restored_callback( $order_id ) {

		$post_type = get_post_type( $order_id );
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) ) {
			$is_knawat = get_post_meta( $order_id, '_knawat_order', true );
			if ( 1 == $is_knawat ) {
				$knawat_ds = $this->knawat_is_order_local_ds( $order_id );
				if ( $knawat_ds != '' ) {
					do_action( $knawat_ds . '_knawat_order_restored', $order_id );
				}
			}
		}
	}

	/**
	 * Check if order is for knawat local DS.
	 *
	 * @param  int $order_id    The ID of the order
	 *
	 * @return string|bool
	 */
	function knawat_is_order_local_ds( $order_id ) {
		if ( empty( $order_id ) ) {
			return false; }
		$knawat_order_ds = get_post_meta( $order_id, '_knawat_order_ds', true );
		$dropshippers    = knawat_dropshipwc_get_dropshippers();
		if ( ! empty( $knawat_order_ds ) && isset( $dropshippers[ $knawat_order_ds ] ) ) {
			return apply_filters( 'knawat_dropshipwc_separate_localds_webhook', $knawat_order_ds );
		}
		return false;
	}

}

$knawat_webhooks = new Knawat_Dropshipping_Woocommerce_Webhook();
