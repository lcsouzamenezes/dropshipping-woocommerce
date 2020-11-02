<?php
/**
 * The order-specific functions of the plugin.
 *
 * @package     Knawat_Dropshipping_Woocommerce
 * @subpackage  Knawat_Dropshipping_Woocommerce/includes
 * @copyright   Copyright (c) 2018, Knawat
 * @since       1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Knawat_Dropshipping_Woocommerce_Orders {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if( !knawat_dropshipwc_is_dokan_active()) {
			// Create Suborder from front-end checkout.
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'knawat_dropshipwc_create_sub_order' ), 10 );

			// Create separate shipping packages for knawat and non-knawat products.
			add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'knawat_dropshipwc_split_knawat_shipping_packages' ) );
			// Add item meta into shipping item.
			add_action( 'woocommerce_checkout_create_order_shipping_item', array( $this, 'knawat_dropshipwc_add_shipping_meta_data' ), 20, 4 );

			/* Order Table */
			add_filter( 'manage_shop_order_posts_columns', array( $this, 'knawat_dropshipwc_shop_order_columns' ), 20 );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'knawat_dropshipwc_render_shop_order_columns' ) );

			/* Order MetaBoxes */
			add_action( 'add_meta_boxes', array( $this, 'knawat_dropshipwc_add_meta_boxes' ), 30 );

			/* Display Main orders only */
			add_action( 'load-edit.php', array( $this, 'knawat_dropshipwc_order_filter' ) );

			/* Count status for parent orders only */
			add_action( 'wp_count_posts', array( $this, 'knawat_dropshipwc_filter_count_orders' ), 10, 3 );
			add_action( 'admin_head', array( $this, 'knawat_dropshipwc_count_processing_order' ), 5 );

			/* Order Status sync */
			add_action( 'woocommerce_order_status_changed', array( $this, 'knawat_dropshipwc_order_status_change' ), 10, 3 );
			add_action( 'woocommerce_order_status_changed', array( $this, 'knawat_dropshipwc_child_order_status_change' ), 99, 3 );

			/* Remove sub orders from WooCommerce reports */
			add_filter( 'woocommerce_reports_get_order_report_query', array( $this, 'knawat_dropshipwc_admin_order_reports_remove_suborders' ) );

			/* WooCommerce Status Dashboard Widget */
			add_filter( 'woocommerce_dashboard_status_widget_top_seller_query', array( $this, 'knawat_dropshipwc_dashboard_status_widget_top_seller_query' ) );

			/* Order Trash, Untrash and Delete Operations. */
			add_action( 'wp_trash_post', array( $this, 'knawat_dropshipwc_trash_order' ) );
			add_action( 'untrash_post', array( $this, 'knawat_dropshipwc_untrash_order' ) );
			add_action( 'delete_post', array( $this, 'knawat_dropshipwc_delete_order' ) );

			/* Override customer orders' query */
			add_filter( 'woocommerce_my_account_my_orders_query', array( $this, 'knawat_dropshipwc_get_customer_main_orders' ) );

			/* Rest API only list Main orders */
			add_filter( 'woocommerce_rest_shop_order_query', array( $this, 'knawat_dropshipwc_rest_shop_order_query' ), 10, 2 );
			add_filter( 'woocommerce_rest_shop_order_object_query', array( $this, 'knawat_dropshipwc_rest_shop_order_query' ), 10, 2 );

			/* Add suborders' ID in REST API order response */
			add_filter( 'woocommerce_rest_prepare_shop_order', array( $this, 'knawat_dropshipwc_add_suborders_api' ), 10, 3 );
			add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'knawat_dropshipwc_add_suborders_api' ), 10, 3 );

			/* Add Local DS in REST API order response */
			add_filter( 'woocommerce_rest_prepare_shop_order', array( $this, 'knawat_dropshipwc_add_localds_api' ), 20, 3 );
			add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'knawat_dropshipwc_add_localds_api' ), 20, 3 );

			/* Disabled Emails for suborders */
			$email_ids = array(
				'new_order',
				'failed_order',
				'cancelled_order',
				'customer_refunded_order',
				'customer_processing_order',
				'customer_on_hold_order',
				'customer_completed_order',
			);

			foreach( $email_ids as $email_id ){
				add_filter( 'woocommerce_email_enabled_' . $email_id, array( $this, 'knawat_dropshipwc_disable_emails' ),10, 2 );
			}
		}else{
			// Add _knawat_order meta data if order contains knawat products
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'knawat_dropshipwc_add_knawat_order_meta_data' ), 10 );
		}

		// Hide the item meta on the Order Items table.
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'knawat_dropshipwc_hide_order_item_meta' ) );

        /**
         *  Manaage Knawat Cost related in order meta and in order item meta.
         */
        // save knawat cost value when edited
		add_action( 'woocommerce_saved_order_items', array( $this, 'save_order_item_cost' ), 10, 2 );

        // update line item knawat cost totals and order knawat cost total when editing an order in the admin
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'process_order_knawat_cost_meta' ), 15 );

        // add line item knawat costs when line items are added in the admin via AJAX
        add_action( 'woocommerce_ajax_add_order_item_meta', array( $this, 'ajax_add_order_line_knawat_cost' ), 10, 2 );

        // set the order meta when an order is placed from standard checkout
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'set_order_knawat_cost_meta' ), 10, 1 );

		// Order Create/update by API
		add_action( 'woocommerce_new_order', array( $this, 'set_order_knawat_cost_meta' ), 10, 1 );
        add_action( 'woocommerce_update_order', array( $this, 'set_order_knawat_cost_meta' ), 10, 1 );

        // add negative cost of good item meta for refunds
		add_action( 'woocommerce_refund_created', array( $this, 'add_refund_order_knawat_costs' ), 10, 2 );

    }

    /**
     * Monitors a new order and attempts to create sub-orders
     *
     * If an order contains products from knawat and non-knawat products then divide it and create a sub-orders of order.
     *
     * @param int $parent_order_id
     * @return void
     *
     * @hooked woocommerce_checkout_update_order_meta - 10
     */
    public function knawat_dropshipwc_create_sub_order( $parent_order_id ) {

        if ( get_post_meta( $parent_order_id, '_knawat_sub_order' ) == true ) {
            $args = array(
                'post_parent' => $parent_order_id,
                'post_type'   => 'shop_order',
                'numberposts' => -1,
                'post_status' => 'any'
            );
            $child_orders = get_children( $args );

            foreach ( $child_orders as $child ) {
                wp_delete_post( $child->ID );
            }
        }
        
        $parent_order         = new WC_Order( $parent_order_id );
        $order_types          = $this->knawat_dropshipwc_order_contains_products( $parent_order_id );

        // return if we've only ONE seller
        if ( count( $order_types ) == 1 ) {
            $temp = array_keys( $order_types );
            $order_type = reset( $temp );
            
            $dropshippers = knawat_dropshipwc_get_dropshippers();
            if( 'knawat' === $order_type || isset( $dropshippers[$order_type ] ) ){
                update_post_meta( $parent_order_id , '_knawat_order', 1 );
                if( isset( $dropshippers[$order_type ] ) ){
                    update_post_meta( $parent_order_id , '_knawat_order_ds', $order_type );
                    $this->knawat_dropshipwc_reduce_localds_stock( $parent_order_id, $order_type );
                }
            }
            return;
        }

        // flag it as it has a suborder
        update_post_meta( $parent_order_id, '_knawat_sub_order', true );

        // seems like we've got knawat and non-knawat orders.
        foreach ( $order_types as $order_key => $order_items ) {
            $this->knawat_dropshipwc_create_type_order( $parent_order, $order_key, $order_items );
        }
    }

    /**
     * Return array of knawat and non-knawat with items
     *
     * @since 1.2.0
     *
     * @param int $order_id
     * @return array $items
     */
    public function knawat_dropshipwc_order_contains_products( $order_id ) {

        $order       = new WC_Order( $order_id );
        $order_items = $order->get_items();

        $items = array();
        foreach ( $order_items as $item ) {
            if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
                $product_id = $item->get_product_id();
                $dropshipping = get_post_meta( $product_id, 'dropshipping', true );
                if( $dropshipping == 'knawat' ){
                    $variation_id = isset( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
                    // get Dropshipper for variation.
                    $dropshipper = get_post_meta( $variation_id, '_knawat_dropshipper', true );
                    if( !empty( $dropshipper ) && $dropshipper != 'default' ){
                        // Get Available Dropshippers
                        $dropshippers = knawat_dropshipwc_get_dropshippers();
                        $dropship_qty = get_post_meta( $variation_id, '_localds_stock', true );
                        if( empty( $dropship_qty ) ){ $dropship_qty = 0; }

                        if( isset( $dropshippers[$dropshipper] ) && $dropship_qty > 0 ){
                            // Get Local DS's Country
                            $countries = $dropshippers[$dropshipper]['countries'];
                            // Get Order Country.
                            $ship_country = $order->get_shipping_country();
                            if( in_array( $ship_country,  $countries ) ){

                                $qty = $item->get_quantity('edit');
                                if( $qty <= $dropship_qty ){
                                    $items[$dropshipper][] = $item;
                                    continue;
                                }else{

                                    $subtotal = $item->get_subtotal( 'edit' );
                                    $subtotal_tax = $item->get_subtotal_tax( 'edit' );
                                    $total = $item->get_total( 'edit' );
                                    $total_tax = $item->get_total_tax( 'edit' );
                                    $taxes = $item->get_taxes();

                                    // Split Item Qty in order. if not enough quantities in LocalDS are there.
                                    $diff_qty = ( $qty - $dropship_qty );
                                    $item1 = clone $item;
                                    $item1->set_quantity( $dropship_qty );
                                    $item1->set_subtotal( ($dropship_qty/$qty)* $subtotal );
                                    $item1->set_subtotal_tax( ($dropship_qty/$qty)* $subtotal_tax );
                                    $item1->set_total( ($dropship_qty/$qty)* $total );
                                    $item1->set_total_tax( ($dropship_qty/$qty)* $total_tax );
                                    $new_taxes = $taxes;
                                    if( !empty( $taxes['total'] ) ){
                                        foreach ( $taxes['total'] as $key => $value) {
                                            $new_taxes['total'][$key] = ( $value * ($dropship_qty/$qty) );
                                        }
                                    }
                                    if( !empty( $taxes['subtotal'] ) ){
                                        foreach ( $taxes['subtotal'] as $key => $value) {
                                            $new_taxes['subtotal'][$key] = ( $value * ($dropship_qty/$qty) );
                                        }
                                    }
                                    $item1->set_taxes( $new_taxes );

                                    $item1->apply_changes();
                                    $items[$dropshipper][] = $item1;

                                    // Package for default ds.
                                    $item2 = clone $item;
                                    $item2->set_quantity( $diff_qty );
                                    $item2->set_subtotal( ($diff_qty/$qty)* $subtotal );
                                    $item2->set_subtotal_tax( ($diff_qty/$qty)* $subtotal_tax );
                                    $item2->set_total( ($diff_qty/$qty)* $total );
                                    $item2->set_total_tax( ($diff_qty/$qty)* $total_tax );

                                    $new_taxes2 = $taxes;
                                    if( !empty( $taxes['total'] ) ){
                                        foreach ( $taxes['total'] as $key => $value) {
                                            $new_taxes2['total'][$key] = ( $value * ($diff_qty/$qty) );
                                        }
                                    }
                                    if( !empty( $taxes['subtotal'] ) ){
                                        foreach ( $taxes['subtotal'] as $key => $value) {
                                            $new_taxes2['subtotal'][$key] = ( $value * ($diff_qty/$qty) );
                                        }
                                    }
                                    $item2->set_taxes( $new_taxes2 );

                                    $item2->apply_changes();
                                    $items['knawat'][] = $item2;
                                    continue;

                                }
                            }
                        }
                    }

                    $items['knawat'][] = $item;
                }else{
                    $items['non_knawat'][] = $item;
                }
            }
        }

        return $items;
    }
    
    /**
     * Creates a sub order
     *
     * @param int $parent_order
     * @param string $order_type
     * @param array $order_items
     */
    public function knawat_dropshipwc_create_type_order( $parent_order, $order_type, $order_items ) {

        $order_data = apply_filters( 'woocommerce_new_order_data', array(
            'post_type'     => 'shop_order',
            'post_title'    => sprintf( __( 'Order &ndash; %s', 'dropshipping-woocommerce' ), strftime( _x( '%b %d, %Y @ %I:%M %p', 'Order date parsed by strftime', 'dropshipping-woocommerce' ) ) ),
            'post_status'   => 'wc-pending',
            'ping_status'   => 'closed',
            'post_excerpt'  => isset( $posted['order_comments'] ) ? $posted['order_comments'] : '',
            'post_author'   => get_post_field( 'post_author', $parent_order->get_id() ),
            'post_parent'   => $parent_order->get_id(),
            'post_password' => uniqid( 'order_' )   // Protects the post just in case
        ) );

        $order_id = wp_insert_post( $order_data );

        if ( $order_id && !is_wp_error( $order_id ) ) {

            $order_total = $order_tax = 0;
            $product_ids = array();
            $items_tax = array();

            // now insert line items
            foreach ( $order_items as $item ) {
                $order_total   += (float) $item->get_total();
                $order_tax     += (float) $item->get_total_tax();
                $product_ids[] = $item->get_product_id();

                $item_id = wc_add_order_item( $order_id, array(
                    'order_item_name' => $item->get_name(),
                    'order_item_type' => 'line_item'
                ) );

                // Mapping item wise tax data for perticular seller products
                $item_taxes = $item->get_taxes();
                foreach( $item_taxes['total'] as $key=>$value ) {
                    $items_tax[$key][] = $value;
                }

                if ( $item_id ) {
                    $item_meta_data = $item->get_data();
                    $meta_key_map = $this->knawat_dropshipwc_get_order_item_meta_map();
                    foreach ( $item->get_extra_data_keys() as $meta_key ) {
                        wc_add_order_item_meta( $item_id, $meta_key_map[$meta_key], $item_meta_data[$meta_key] );
                    }
                }
            }

            $bill_ship = array(
                '_billing_country', '_billing_first_name', '_billing_last_name', '_billing_company',
                '_billing_address_1', '_billing_address_2', '_billing_city', '_billing_state', '_billing_postcode',
                '_billing_email', '_billing_phone', '_shipping_country', '_shipping_first_name', '_shipping_last_name',
                '_shipping_company', '_shipping_address_1', '_shipping_address_2', '_shipping_city',
                '_shipping_state', '_shipping_postcode'
            );

            // save billing and shipping address
            foreach ( $bill_ship as $val ) {
                $order_key = 'get_' . ltrim( $val, '_' );
                update_post_meta( $order_id, $val, $parent_order->$order_key() );
            }

            // do shipping
            $shipping_values = $this->knawat_dropshipwc_create_sub_order_shipping( $parent_order, $order_id, $order_items, $order_type );
            $shipping_cost   = $shipping_values['cost'];
            $shipping_tax    = $shipping_values['tax'];
            $shipping_taxes    = $shipping_values['taxes'];

            // do tax
            $splited_order = wc_get_order( $order_id );
            
            foreach( $parent_order->get_items( array( 'tax' ) ) as $tax ) {
                $item_id = wc_add_order_item( $order_id, array(
                    'order_item_name' => $tax->get_name(),
                    'order_item_type' => 'tax'
                ) );

                $shipping_tax_amount = isset( $shipping_taxes['taxes']['total'][$tax->get_rate_id()] ) ? $shipping_taxes['taxes']['total'][$tax->get_rate_id()] : $shipping_tax;
                $tax_metas = array(
                    'rate_id'             => $tax->get_rate_id(),
                    'label'               => $tax->get_label(),
                    'compound'            => $tax->get_compound(),
                    'tax_amount'          => wc_format_decimal( array_sum( $items_tax[$tax->get_rate_id()] ) ),
                    'shipping_tax_amount' => $shipping_tax_amount
                );

                foreach( $tax_metas as $meta_key => $meta_value ) {
                    wc_add_order_item_meta( $item_id, $meta_key, $meta_value );
                }

            }

            // add coupons if any
            $this->knawat_dropshipwc_create_sub_order_coupon( $parent_order, $order_id, $product_ids );
            $discount = $this->knawat_dropshipwc_sub_order_get_total_coupon( $order_id );

            // calculate the total
            $order_in_total = $order_total + $shipping_cost + $order_tax + $shipping_tax;
            
            // set order meta
            update_post_meta( $order_id, '_payment_method',         $parent_order->get_payment_method() );
            update_post_meta( $order_id, '_payment_method_title',   $parent_order->get_payment_method_title() );

            update_post_meta( $order_id, '_order_shipping',         wc_format_decimal( $shipping_cost ) );
            update_post_meta( $order_id, '_order_discount',         wc_format_decimal( $discount ) );
            update_post_meta( $order_id, '_cart_discount',          wc_format_decimal( $discount ) );
            update_post_meta( $order_id, '_order_tax',              wc_format_decimal( $order_tax ) );
            update_post_meta( $order_id, '_order_shipping_tax',     wc_format_decimal( $shipping_tax ) );
            update_post_meta( $order_id, '_order_total',            wc_format_decimal( $order_in_total ) );
            update_post_meta( $order_id, '_order_key',              apply_filters('woocommerce_generate_order_key', uniqid('order_') ) );
            update_post_meta( $order_id, '_customer_user',          $parent_order->get_customer_id() );
            update_post_meta( $order_id, '_order_currency',         get_post_meta( $parent_order->get_id(), '_order_currency', true ) );
            update_post_meta( $order_id, '_prices_include_tax',     $parent_order->get_prices_include_tax() );
            update_post_meta( $order_id, '_customer_ip_address',    get_post_meta( $parent_order->get_id(), '_customer_ip_address', true ) );
            update_post_meta( $order_id, '_customer_user_agent',    get_post_meta( $parent_order->get_id(), '_customer_user_agent', true ) );
            
            $dropshippers = knawat_dropshipwc_get_dropshippers();
            if( 'knawat' === $order_type || isset( $dropshippers[$order_type ] ) ){
                update_post_meta( $order_id , '_knawat_order', 1 );
                if( isset( $dropshippers[$order_type ] ) ){
                    update_post_meta( $order_id , '_knawat_order_ds', $order_type );
                    $this->knawat_dropshipwc_reduce_localds_stock( $order_id, $order_type );
                }
            }

            do_action( 'woocommerce_new_order', $order_id );
            do_action( 'knawat_dropshipwc_checkout_update_order_meta', $order_id, $order_type );
        } // if order
    }

    /**
     * Map meta data for new item meta keys
     *
     */
    public function knawat_dropshipwc_get_order_item_meta_map() {
        return apply_filters( 'knawat_dropshipwc_get_order_item_meta_keymap', array(
            'product_id'   => '_product_id',
            'variation_id' => '_variation_id',
            'quantity'     => '_qty',
            'tax_class'    => '_tax_class',
            'subtotal'     => '_line_subtotal',
            'subtotal_tax' => '_line_subtotal_tax',
            'total'        => '_line_total',
            'total_tax'    => '_line_tax',
            'taxes'        => '_line_tax_data'
        ) );
    }
        
    /**
     * Create shipping for a sub-order if neccessary
     *
     * @param WC_Order $parent_order
     * @param int $order_id
     * @param array $product_ids
     * @return type
     */
    public function knawat_dropshipwc_create_sub_order_shipping( $parent_order, $order_id, $order_items, $order_type ) {

        $t_cost = $t_total_tax = 0;
        $total_taxs = array( 'taxes' => array( 'total' => array() ) );
        foreach( $parent_order->get_items( array( 'shipping' ) ) as $shipping ) {

            $ship_data = $shipping->get_data();
            $ship_meta_data = isset( $ship_data['meta_data'] ) ? $ship_data['meta_data'] : array();
            $package_type = 'non_knawat';
            foreach( $ship_meta_data as $ship_meta ){
                $ship_meta = $ship_meta->get_data();
                if( isset( $ship_meta['key'] ) && '_package_type' === $ship_meta['key'] ){
                    $package_type = sanitize_text_field( $ship_meta['value'] );
                }
            }
            if( $order_type != $package_type ){
                continue;
            }

            $item_id = wc_add_order_item( $order_id, array(
                'order_item_name' => $shipping->get_name(),
                'order_item_type' => 'shipping'
            ) );

            $shipping_metas = array(
                'method_id'    => isset( $ship_data['method_id'] ) ? $ship_data['method_id'] : '',
                'cost'         => isset( $ship_data['total'] ) ? $ship_data['total'] : '',
                'total_tax'    => isset( $ship_data['total_tax'] ) ? $ship_data['total_tax'] : '',
                'taxes'        => isset( $ship_data['taxes'] ) ? $ship_data['taxes'] : array(),
            );

            foreach( $shipping_metas as $meta_key => $meta_value ) {
                wc_add_order_item_meta( $item_id, $meta_key, $meta_value );
            }

            foreach( $ship_meta_data as $ship_meta ){
                $ship_meta = $ship_meta->get_data();
                if( isset( $ship_meta['key'] ) && isset( $ship_meta['value'] ) ){
                    wc_add_order_item_meta( $item_id, $ship_meta['key'], $ship_meta['value'] );
                }
            }
            $t_cost += $shipping_metas['cost'];
            $t_total_tax += $shipping_metas['total_tax'];
            if( !empty( $ship_data['taxes']['total'] ) ){
                foreach ( $ship_data['taxes']['total'] as $key => $value ) {
                    echo $key;
                    echo $value;
                    if( isset( $total_taxs['taxes']['total'][$key] ) ){
                        $total_taxs['taxes']['total'][$key] += $value;
                    }else{
                        $total_taxs['taxes']['total'][$key] = $value;
                    }
                }
            }

        }

        return array( 'cost' => $t_cost, 'tax' => $t_total_tax, 'taxes' => $total_taxs );
    }

    /**
     * Create coupons for a sub-order if neccessary
     *
     * @param WC_Order $parent_order
     * @param int $order_id
     * @param array $product_ids
     * @return type
     */
    public function knawat_dropshipwc_create_sub_order_coupon( $parent_order, $order_id, $product_ids ) {
        if ( version_compare( WC_VERSION, '3.7', '<' ) ) {
            $used_coupons = $parent_order->get_used_coupons();
        }else{
            $used_coupons = $parent_order->get_coupon_codes();
        }
        
        if ( ! count( $used_coupons ) ) {
            return;
        }

        if ( $used_coupons ) {
            foreach ($used_coupons as $coupon_code) {
                $coupon = new WC_Coupon( $coupon_code );
                
                if ( $coupon && !is_wp_error( $coupon ) && array_intersect( $product_ids, $coupon->get_product_ids() ) ) {

                    // we found some match
                    $item_id = wc_add_order_item( $order_id, array(
                        'order_item_name' => $coupon_code,
                        'order_item_type' => 'coupon'
                    ) );

                    // Add line item meta
                    if ( $item_id ) {
                        wc_add_order_item_meta( $item_id, 'discount_amount', isset( WC()->cart->coupon_discount_amounts[ $coupon_code ] ) ? WC()->cart->coupon_discount_amounts[ $coupon_code ] : 0 );
                    }
                }
            }
        }
    }

    /**
     * Get discount coupon total from a order
     *
     * @global WPDB $wpdb
     * @param int $order_id
     * @return int
     */
    public function knawat_dropshipwc_sub_order_get_total_coupon( $order_id ) {
        global $wpdb;

        $sql = $wpdb->prepare( "SELECT SUM(oim.meta_value) FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
                LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON oim.order_item_id = oi.order_item_id
                WHERE oi.order_id = %d AND oi.order_item_type = 'coupon'", $order_id );

        $result = $wpdb->get_var( $sql );
        if ( $result ) {
            return $result;
        }

        return 0;
    }
   
    /**
     * Split all shipping class 'A' products in a separate package
     */
    public function knawat_dropshipwc_split_knawat_shipping_packages( $packages ) {

        // Reset all packages
        $packages              = array();
        $regular_package_items = array();
        $split_package_items   = array();
        $localds_package_items = array();

        // Split these products in a separate package
        foreach ( WC()->cart->get_cart() as $item_key => $item ) {
            if ( $item['data']->needs_shipping() ) {
                $dropshipping = get_post_meta( $item['product_id'], 'dropshipping', true );
                if ( 'knawat' === $dropshipping ) {
                    $variation_id = isset( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];

                    // get Dropshipper for variation.
                    $dropshipper = get_post_meta( $variation_id, '_knawat_dropshipper', true );
                    if( !empty( $dropshipper ) && $dropshipper != 'default' ){
                        // Get Available Dropshippers
                        $dropshippers = knawat_dropshipwc_get_dropshippers();
                        $dropship_qty = get_post_meta( $variation_id, '_localds_stock', true );
                        if( empty( $dropship_qty ) ){ $dropship_qty = 0; }

                        if( isset( $dropshippers[$dropshipper] ) && $dropship_qty > 0 ){
                            // Get Local DS's Country
                            $countries = $dropshippers[$dropshipper]['countries'];
                            // Get Shipping Country.
                            $ship_country = WC()->customer->get_shipping_country();
                            if( in_array( $ship_country,  $countries ) ){
                                $qty = $item['quantity'];
                                if( $qty <= $dropship_qty ){
                                    $localds_package_items[$dropshipper][ $item_key ] = $item;
                                    continue;
                                }else{
                                    // Split Item Qty in Packages. if not enough quantities in localds are there.
                                    $diff_qty = ( $qty - $dropship_qty );
                                    $item['quantity'] = $dropship_qty;
                                    $localds_package_items[$dropshipper][ $item_key ] = $item;

                                    // Package for default ds.
                                    $item['quantity'] = $diff_qty;
                                    $split_package_items[ $item_key ] = $item;
                                    continue;
                                }
                            }
                        }
                    }
                    $split_package_items[ $item_key ] = $item;
                } else {
                    $regular_package_items[ $item_key ] = $item;
                }
            }
        }

        // Create shipping packages
        if ( $regular_package_items ) {
            $packages[] = array(
                'contents'        => $regular_package_items,
                'contents_cost'   => array_sum( wp_list_pluck( $regular_package_items, 'line_total' ) ),
                'applied_coupons' => WC()->cart->get_applied_coupons(),
                'user'            => array(
                    'ID' => get_current_user_id(),
                ),
                'destination'    => array(
                    'country'    => WC()->customer->get_shipping_country(),
                    'state'      => WC()->customer->get_shipping_state(),
                    'postcode'   => WC()->customer->get_shipping_postcode(),
                    'city'       => WC()->customer->get_shipping_city(),
                    'address'    => WC()->customer->get_shipping_address(),
                    'address_2'  => WC()->customer->get_shipping_address_2()
                )
            );
        }

        if ( $split_package_items ) {
            $packages[] = array(
                'contents'        => $split_package_items,
                'contents_cost'   => array_sum( wp_list_pluck( $split_package_items, 'line_total' ) ),
                'applied_coupons' => WC()->cart->get_applied_coupons(),
                'user'            => array(
                    'ID' => get_current_user_id(),
                ),
                'package_type'  => 'knawat',
                'destination'    => array(
                    'country'    => WC()->customer->get_shipping_country(),
                    'state'      => WC()->customer->get_shipping_state(),
                    'postcode'   => WC()->customer->get_shipping_postcode(),
                    'city'       => WC()->customer->get_shipping_city(),
                    'address'    => WC()->customer->get_shipping_address(),
                    'address_2'  => WC()->customer->get_shipping_address_2()
                )
            );
        }

        if ( !empty( $localds_package_items ) ) {
            foreach ($localds_package_items as $dropship => $localds_package_item ) {
                $packages[] = array(
                    'contents'        => $localds_package_item,
                    'contents_cost'   => array_sum( wp_list_pluck( $localds_package_item, 'line_total' ) ),
                    'applied_coupons' => WC()->cart->get_applied_coupons(),
                    'user'            => array(
                        'ID' => get_current_user_id(),
                    ),
                    'package_type' => $dropship,
                    'destination'    => array(
                        'country'    => WC()->customer->get_shipping_country(),
                        'state'      => WC()->customer->get_shipping_state(),
                        'postcode'   => WC()->customer->get_shipping_postcode(),
                        'city'       => WC()->customer->get_shipping_city(),
                        'address'    => WC()->customer->get_shipping_address(),
                        'address_2'  => WC()->customer->get_shipping_address_2()
                    )
                );
            }
        }
        return $packages;
    }

    /**
     * Action hook to adjust item before save.
     *
     * @since 3.0.0
     */
    public function knawat_dropshipwc_add_shipping_meta_data( $item, $package_key, $package, $order ){

        if( empty( $item ) ){
            return;
        }

        if( isset( $package['package_type'] ) ){
            $item->add_meta_data( '_package_type', sanitize_text_field( $package['package_type'] ), true );
        }
    }

    /**
	 * Hide cost of goods meta data fields from the order admin
	 */
	public function knawat_dropshipwc_hide_order_item_meta( $hidden_fields ) {
		return array_merge( $hidden_fields, array( '_package_type', '_knawat_item_cost', '_knawat_item_total_cost' ) );
    }
    
    /**
     * Add sub-orders in order table column
     *
     * @param $order_columns The order table column
     *
     * @return string           The label value
     */
    public function knawat_dropshipwc_shop_order_columns( $order_columns ) {

        if ( version_compare( WC_VERSION, '3.3', '>=' ) ) {
            $order_number_col_name = 'order_number';
        }else{
            $order_number_col_name = 'order_title';
        }
        $suborder      = array( 'kwd_suborder' => _x( 'Suborders', 'Admin: Order table column', 'dropshipping-woocommerce' ) );
        $ref_pos       = array_search( $order_number_col_name, array_keys( $order_columns ) );
        $order_columns = array_slice( $order_columns, 0, $ref_pos + 1, true ) + $suborder + array_slice( $order_columns, $ref_pos + 1, count( $order_columns ) - 1, true );
        
        return $order_columns;
    }

    /**
     * Output custom columns for suborders
     *
     * @param  string $column
     */
    public function knawat_dropshipwc_render_shop_order_columns( $column, $order = false ) {
        global $post;
        if ( empty( $order ) ) {
            $order = wc_get_order( $post->ID );
        }
        $order_id = $order->get_id();

        switch ( $column ) {
            case 'kwd_suborder' :
                $suborder_ids = $this->knawat_dropshipwc_get_suborder( $order_id );

                if ( $suborder_ids ) {
                    foreach ( $suborder_ids as $suborder_id ) {
                        $suborder          = wc_get_order( $suborder_id );
                        $order_uri         = esc_url( 'post.php?post=' . absint( $suborder_id ) . '&action=edit' );
                        $order_status_name = wc_get_order_status_name( $suborder->get_status() );
                        if ( version_compare( WC_VERSION, '3.3', '>=' ) ) {
                            printf( '<div class="kwd_suborder_item"><strong><a href="%s">#%s</a></strong> <mark class="order-status status-%s"><span>%s</span></mark></div>',
                                $order_uri,
                                $suborder->get_order_number(),
                                sanitize_title( $suborder->get_status() ),
                                $order_status_name
                            );
                        }else{
                            printf( '<div class="kwd_suborder_item order_status column-order_status" style="display:block"><mark class="%s tips" data-tip="%s">%s</mark> <strong><a class="row-title" href="%s">#%s</a></strong></div>', 
                                esc_attr( sanitize_html_class( $suborder->get_status() ) ),
                                $order_status_name,
                                $order_status_name,
                                $order_uri,
                                $suborder->get_order_number()
                            );
                        }
                    }
                } else {
                    echo '<span>&ndash;</span>';
                }

                break;
        }
    }

    /**
     * Get suborder from parent_order_id
     *
     *
     * @param bool|int $parent_order_id The parent id order
     *
     * @return array $suborder_ids
     */
    public function knawat_dropshipwc_get_suborder( $parent_order_id = false ) {
        $suborder_ids = array();
        if ( $parent_order_id ) {
            global $wpdb;

            $suborder_ids  = $wpdb->get_col(
                $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'shop_order'", $parent_order_id )
            );

        }
        return apply_filters( 'knawat_dropshipwc_get_suborder_ids', $suborder_ids, $parent_order_id );
    }

    /**
     * Add suborders metaboxe for order screen
     *
     * @return void
     */
    public function knawat_dropshipwc_add_meta_boxes() {
        if ( 'shop_order' != get_current_screen()->id ) {
            return;
        }

        global $post;
        $is_parentorder = $this->knawat_dropshipwc_get_suborder( absint( $post->ID ) );
        $is_suborder  = wp_get_post_parent_id( absint( $post->ID ) );
        $is_localds = get_post_meta( $post->ID, '_knawat_order_ds', true );

        if ( $is_parentorder ) {
            add_meta_box( 'knawat_dropshipwc-suborders', __( 'Suborders', 'dropshipping-woocommerce' ), array( $this, 'knawat_dropshipwc_render_order_metabox' ), 'shop_order', 'side', 'high' );
        } else if ( $is_suborder ) {
            add_meta_box( 'knawat_dropshipwc-parent-order',  __( 'Parent order', 'dropshipping-woocommerce' ), array( $this, 'knawat_dropshipwc_render_order_metabox' ), 'shop_order', 'side', 'high' );
        }

        if( $is_localds != '' ){
            $dropshippers = knawat_dropshipwc_get_dropshippers();
            if( isset( $dropshippers[$is_localds] ) ){
                add_meta_box( 'knawat_dropshipwc-order-ds',  __( 'Knawat Dropshipper', 'dropshipping-woocommerce' ), array( $this, 'knawat_dropshipwc_render_order_dropshipper_metabox' ), 'shop_order', 'side', 'high' );
            }
        }
    }

    /**
     * Render the order metaboxes
     *
     * @param $post     The post object
     * @param $param    Callback args
     *
     * @return void
     */
    public function knawat_dropshipwc_render_order_metabox( $post, $args ) {
        
        switch ( $args['id'] ) {
            case 'knawat_dropshipwc-suborders':
                $suborder_ids = $this->knawat_dropshipwc_get_suborder( absint( $post->ID ) );
                foreach ( $suborder_ids as $suborder_id ) {
                    $suborder     = wc_get_order( absint( $suborder_id ) );
                    $suborder_uri = esc_url( 'post.php?post=' . absint( $suborder_id ) . '&action=edit' );
                    if ( version_compare( WC_VERSION, '3.3', '>=' ) ) {
                        printf( '<div class="kwd_suborder_item"><strong><a href="%s">#%s</a></strong> <mark class="order-status status-%s"><span>%s</span></mark></div>',
                            $suborder_uri,
                            $suborder->get_order_number(),
                            sanitize_title( $suborder->get_status() ),
                            wc_get_order_status_name( $suborder->get_status() )
                        );
                    }else{
                        echo '<div class="widefat">';
                        printf( '<div class="kwd_suborder_item order_status column-order_status"><mark class="%s tips" data-tip="%s">%s</mark> <strong><a class="row-title" href="%s">#%s</a></strong></div>',
                            esc_attr( sanitize_html_class( $suborder->get_status() ) ),
                            esc_attr( wc_get_order_status_name( $suborder->get_status() ) ),
                            esc_html( wc_get_order_status_name( $suborder->get_status() ) ),
                            $suborder_uri,
                            $suborder->get_order_number()
                        );
                        echo '</div>';
                    }
                }
                break;

            case 'knawat_dropshipwc-parent-order':
                $parent_order_id  = wp_get_post_parent_id( absint( $post->ID ) );
                $parent_order_uri = esc_url( 'post.php?post=' . absint( $parent_order_id ) . '&action=edit' );
                printf( '<a href="%s">&#8592; %s (#%s)</a>', $parent_order_uri, __( 'Return to main order', 'dropshipping-woocommerce' ), $parent_order_id );
                break;
           
        }
    }

    /**
     * Render the order Dropshipper metaboxes
     *
     * @param $post     The post object
     * @param $param    Callback args
     *
     * @return void
     */
    public function knawat_dropshipwc_render_order_dropshipper_metabox( $post, $args ) {
        $is_localds = get_post_meta( $post->ID, '_knawat_order_ds', true );
        $dropshippers = knawat_dropshipwc_get_dropshippers();
        if( isset( $dropshippers[$is_localds] ) ){
            $dropshipper_name = $dropshippers[$is_localds]['name'];
            echo '<p><strong>'.$dropshipper_name.'</strong></p>';
        }
    }

    /**
	 * Add `posts_where` filter if knawat orders need to filter
	 *
	 * @since 1.0
	 * @return void
	 */
	function knawat_dropshipwc_order_filter(){
	    global $typenow;
	    if( 'shop_order' != $typenow ){
	        return;
        }
        if ( !isset( $_GET[ 'knawat_orders' ] ) ){
            add_filter( 'posts_where' , array( $this, 'knawat_dropshipwc_posts_where_orders') );
        }
    }
    
    /**
	 * Add condtion in WHERE statement for filter only main orders in orders list table
	 *
	 * @since  1.0
	 * @param  string $where Where condition of SQL statement for orders query
	 * @return string $where Modified Where condition of SQL statement for orders query
	 */
	function knawat_dropshipwc_posts_where_orders( $where ){
	    global $wpdb;
        $where .= " AND {$wpdb->posts}.post_parent = 0";
	    return $where;	
	}

    /**
	 * Modify returned post counts by status for the shop_order
	 *
	 * @since 1.2.0
	 *
	 * @param object $counts An object containing the current post_type's post
	 *                       counts by status.
	 * @param string $type   Post type.
	 * @param string $perm   The permission to determine if the posts are 'readable'
	 *                       by the current user.
     * 
     * @return object $counts Modified post counts by status.
	 */
    public function knawat_dropshipwc_filter_count_orders( $counts, $type, $perm ) {
        if( 'shop_order' === $type && is_admin() && ( 'edit-shop_order' === get_current_screen()->id || 'dashboard' === get_current_screen()->id ) ){
            global $wpdb;
            
            if ( ! post_type_exists( $type ) )
		        return new stdClass;

            $query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = 0";
            if ( 'readable' == $perm && is_user_logged_in() ) {
                $post_type_object = get_post_type_object($type);
                if ( ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
                    $query .= $wpdb->prepare( " AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))",
                        get_current_user_id()
                    );
                }
            }
            $query .= ' GROUP BY post_status';

            $results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
            $counts = array_fill_keys( get_post_stati(), 0 );

            foreach ( $results as $row ) {
                $counts[ $row['post_status'] ] = $row['num_posts'];
            }

            $counts = (object) $counts;
        }
        return $counts;
    }

    /**
	 * Modify returned order counts by status
	 *
	 * @since 1.2.0
	 *
	 */
    public function knawat_dropshipwc_count_processing_order() {
        global $wpdb;

        $count = 0;
        $status = 'wc-processing';
        $order_statuses = array_keys( wc_get_order_statuses() );
        if ( ! in_array( $status, $order_statuses, true ) ) {
            return 0;
        }

        $cache_key    = WC_Cache_Helper::get_cache_prefix( 'orders' ) . $status;
        $cached_count = wp_cache_get( 'kwdcounts_' . $cache_key, 'kwdcounts' );
        if ( $cached_count ) {
            return;
        }

        foreach ( wc_get_order_types( 'order-count' ) as $type ) {
            $data_store = WC_Data_Store::load( 'shop_order' === $type ? 'order' : $type );
            if ( $data_store ) {
                $count += absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( * ) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = %s AND post_parent = 0", $status ) ) );
            }
        }
        wp_cache_set( $cache_key, $count, 'counts' );
        wp_cache_set( 'kwdcounts_' . $cache_key, true, 'kwdcounts' );
    }

    /**
     * Update the child order status when a parent order status is changed
     *
     * @global object $wpdb
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     */
    public function knawat_dropshipwc_order_status_change( $order_id, $old_status, $new_status ) {
        global $wpdb;

        // check for wc- prefix. add if its not there.
        if ( stripos( $new_status, 'wc-' ) === false ) {
            $new_status = 'wc-' . $new_status;
        }

        // if any child orders found, change the orders as well
        $sub_orders = $this->knawat_dropshipwc_get_suborder( $order_id );
        if ( $sub_orders ) {
            foreach ( $sub_orders as $order_post ) {
                $order = new WC_Order( $order_post );
                $order->update_status( $new_status );
            }
        }
    }

    /**
     * Mark the parent order as complete when all the child order are completed
     *
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     * @return void
     */
    public function knawat_dropshipwc_child_order_status_change( $order_id, $old_status, $new_status ) {
        $order_post = get_post( $order_id );

        // Check for child orders only
        if ( $order_post->post_parent === 0 ) {
            return;
        }

        // get all the child orders and monitor the status
        $parent_order_id = $order_post->post_parent;
        $sub_order_ids   = $this->knawat_dropshipwc_get_suborder( $parent_order_id );

        // return if any child order is not completed
        $all_complete = true;
        $all_refunded = true;

        if ( $sub_order_ids ) {
            foreach ($sub_order_ids as $sub_id ) {
                $order = new WC_Order( $sub_id );
                if ( version_compare( WC_VERSION, '2.7', '>' ) ) {
                    $order_status = $order->get_status();
                }else{
                    $order_status = $order->status;
                }
                if ( $order_status != 'completed' ) {
                    $all_complete = false;
                }
                if ( $order_status != 'refunded' ) {
                    $all_refunded = false;
                }
            }
        }

        // seems like all the child orders are completed
        // mark the parent order as complete
        if ( $all_complete ) {
            $parent_order = new WC_Order( $parent_order_id );
            $parent_order->update_status( 'wc-completed', __( 'Mark main order completed when all suborders are completed.', 'dropshipping-woocommerce' ) );
        }

        // mark the parent order as refunded
        if ( $all_refunded ) {
            $parent_order = new WC_Order( $parent_order_id );
            $parent_order->update_status( 'wc-refunded', __( 'Mark main order refunded when all suborders are refunded.', 'dropshipping-woocommerce' ) );
        }
    }

    /**
     * Remove sub orders from WC reports
     *
     * @param array $query
     * @return array
     */
    public function knawat_dropshipwc_admin_order_reports_remove_suborders( $query ) {

        if( false !== strpos( $query['where'], 'shop_order_refund' ) && false !== strpos( $query['where'], 'parent' ) ){
            $query['where'] .= ' AND parent.post_parent = 0';
        }else{
            $query['where'] .= ' AND posts.post_parent = 0';
        }

        return $query;
    }

    /**
     * Filter TopSeller query for WooCommerce Dashboard Widget
     *
     * @param Array $query
     *
     * @return Array $query Altered Array 
     */
    public function knawat_dropshipwc_dashboard_status_widget_top_seller_query( $query ){
        $query['where']  .= " AND posts.post_parent = 0 ";
        return $query;
    }

    /**
     * Delete sub orders when parent order is trashed
     *
     * @param int $post_id
     */
    function knawat_dropshipwc_trash_order( $post_id ) {
        $post = get_post( $post_id );

        if ( $post->post_type == 'shop_order' && $post->post_parent == 0 ) {
            $sub_order_ids = $this->knawat_dropshipwc_get_suborder( $post_id );

            if ( !empty( $sub_order_ids ) ){
                foreach ($sub_order_ids as $sub_order_id ) {
                    wp_trash_post( $sub_order_id );
                }
            }
        }
    }

    /**
     * Untrash sub orders when parent orders are untrashed
     *
     * @param int $post_id
     */
    function knawat_dropshipwc_untrash_order( $post_id ) {
        $post = get_post( $post_id );
        
        if ( $post->post_type == 'shop_order' && $post->post_parent == 0 ) {
            $sub_order_ids = $this->knawat_dropshipwc_get_suborder( $post_id );
            
            if ( !empty( $sub_order_ids ) ){
                foreach ( $sub_order_ids as $sub_order_id ) {
                    wp_untrash_post( $sub_order_id );
                }
            }
        }
    }

    /**
     * Delete sub orders and when parent order is deleted
     *
     * @param int $post_id
     */
    function knawat_dropshipwc_delete_order( $post_id ) {
        $post = get_post( $post_id );

        if ( $post->post_type == 'shop_order' ) {
            
            $sub_order_ids = $this->knawat_dropshipwc_get_suborder( $post_id );

            if ( !empty( $sub_order_ids ) ){
                foreach ($sub_order_ids as $sub_order_id ) {
                    wp_delete_post( $sub_order_id );
                }
            }
        }
    }

    /**
     * Disable email for suborders.
     *
     * @param bool      $is_enabled     Email is enabled or not.
     * @param object    $object         Object this email is for, for example a customer, product, or email.
     *
     * @return bool
     */
    public function knawat_dropshipwc_disable_emails( $is_enabled, $object ){
        if( !empty( $object ) && is_a( $object, 'WC_Order' ) ){
            $order_id = $object->get_id();
            $parent_id = wp_get_post_parent_id( $order_id );
            if( $parent_id > 0 ){
                return false;
            }
        }
        return $is_enabled;
    }

    /**
     * Override Customer Orders array
     *
     * @param array customer orders args query
     *
     * @return array modified customer orders args query
     */
    public function knawat_dropshipwc_get_customer_main_orders( $customer_orders ) {
        $customer_orders['post_parent'] = 0;
        return $customer_orders;
    }

    /**
     * Override Customer Orders array
     *
     * @param $args args query for list orders
     * @param $request WP REST API Request
     *
     * @return array modified orders args query
     */
    public function knawat_dropshipwc_rest_shop_order_query( $args, $request ){
        $method = $request->get_method();
        $route = $request->get_route();
        if( 'GET' === $method && in_array( $route, array( '/wc/v1/orders', '/wc/v2/orders' ) ) ){
            if( !empty( $args ) ){
                if( empty( $args['post_parent__in'] ) ){
                    $args['post_parent__in'] = array( 0 );
                }
            }
        }
        return $args;
    }

    /**
	 * Add 'suborders' to the REST API.
	 *
	 * @since    1.2.0
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post $post Post object.
	 * @param \WP_REST_Request $request Request object.
	 * @return object updated response object
	 */
	function knawat_dropshipwc_add_suborders_api( $response, $post, $request ){

		if( empty( $response->data ) ){
			return $response;
		}

        $suborder_ids = array();
        
        // manual fix for $post->get_parent_id() to check if it exists.
        if( $post && method_exists($post, 'get_parent_id') && 0 == $post->get_parent_id() ){
            $suborder_ids = $this->knawat_dropshipwc_get_suborder( $post->get_id() );
        }

        if( !empty( $suborder_ids ) ){
            $response->data['suborders'] = $suborder_ids;
        }

		return $response;
    }

    /**
     * Add 'Local DS' id and Name to the REST API.
     *
     * @since    1.2.0
     *
     * @param \WP_REST_Response $response The response object.
     * @param \WP_Post $post Post object.
     * @param \WP_REST_Request $request Request object.
     * @return object updated response object
     */
    function knawat_dropshipwc_add_localds_api( $response, $post, $request ){

        if( empty( $response->data ) ){
            return $response;
        }

        // manual fix for $post->get_id() to check if it exists.
        if (method_exists($post,'get_id')){
            $knawat_order_ds = get_post_meta( $post->get_id(), '_knawat_order_ds', true );
	  }
        if( !empty( $knawat_order_ds ) ){
            $dropshippers = knawat_dropshipwc_get_dropshippers();
            if( isset( $dropshippers[$knawat_order_ds] ) ){
                $response->data['order_dropshipper_id'] = $knawat_order_ds;
                $response->data['order_dropshipper_name'] = isset( $dropshippers[$knawat_order_ds]['name'] ) ? $dropshippers[$knawat_order_ds]['name'] : '';
            }
        }

        return $response;
    }


    /**
	 * Save order item knawat cost data over editing order items over Ajax
	 *
	 * @since 1.2.0
	 * @param int $order_id order ID
	 * @param array $items line item data
	 */
	public function save_order_item_cost( $order_id, $items ) {

        $order = wc_get_order( $order_id );
        if( !empty( $order ) ){
            $this->update_knawat_cost_meta_for_order( $order );
        }
    }

    /**
	 * Update knawat cost meta data for orders and order items.
	 *
	 * @since 1.2.0
	 * @param object $order order
	 */
	public function update_knawat_cost_meta_for_order( $order ) {
        $items = $order->get_items();
		if( empty( $items ) ) { return; }
		$order_total_cost = 0;
		foreach ( $items as $item ) {

			if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
				$product_id = $item->get_variation_id();
				if( empty( $product_id ) || $product_id == 0 ){
					$product_id = $item->get_product_id();
				}
                $knawat_cost = get_post_meta( $product_id, '_knawat_cost', true );
                if ( !is_numeric( $knawat_cost ) ){
                    $order_total_cost += 0;
                }else{
                    $item_id = $item->get_id();
                    $item_quantity = $item->get_quantity();
                    $total_cost = $knawat_cost * $item_quantity;
    
                    $order_total_cost += $total_cost;
    
                    if( $knawat_cost > 0 && $item_id > 0 ){
                        wc_update_order_item_meta( $item_id, '_knawat_item_cost', wc_format_decimal( $knawat_cost, 4 ) );
                        wc_update_order_item_meta( $item_id, '_knawat_item_total_cost', wc_format_decimal( $total_cost, 4 ) );
                    } 
                }
			}
        }
        // update the order total cost
		update_post_meta( $order->get_id(), '_knawat_order_total_cost', wc_format_decimal( $order_total_cost, wc_get_price_decimals() ) );
    }

    /**
	 * Update the order line item knawat cost totals and the order knawat cost total when editing
	 * an order in the admin.
	 *
	 * @since 1.2.0
	 * @param int $post_id the post ID for the order
	 */
	public function process_order_knawat_cost_meta( $post_id ) {

		// nonce check
		if ( empty( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( $_POST['woocommerce_meta_nonce'], 'woocommerce_save_data' ) ) {
			return;
		}

        $order = wc_get_order( $post_id );
        if( !empty( $order ) ){
            $this->update_knawat_cost_meta_for_order( $order );
        }
	}


	/**
	 * Update the line item cost and cost total when items are added in the admin edit order page via AJAX
	 *
	 */
	public function ajax_add_order_line_knawat_cost( $item_id, $item ) {

        if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
			$product_id = $item->get_variation_id();
			if( empty( $product_id ) || $product_id == 0 ){
				$product_id = $item->get_product_id();
			}
			$knawat_cost = get_post_meta( $product_id, '_knawat_cost', true );
			if( $knawat_cost > 0 && $item_id > 0 ){
				wc_update_order_item_meta( $item_id, '_knawat_item_cost', wc_format_decimal( $knawat_cost, 4 ) );
				wc_update_order_item_meta( $item_id, '_knawat_item_total_cost', wc_format_decimal( $knawat_cost, 4 ) );
			}
		}
    }

    /**
	 * Set the knawat cost meta for a given order.
	 *
	 * @param int $order_id The order ID.
	 */
	public function set_order_knawat_cost_meta( $order_id ) {
        $order = wc_get_order( $order_id );
        if( !empty( $order ) ){
            $this->update_knawat_cost_meta_for_order( $order );
        }
	}

    /**
	 * Add order knawat costs to a refund meta and refund item meta
     *
     * @param int $refund_id Refund Id.
	 */
	public function add_refund_order_knawat_costs( $refund_id, $args ) {

		$refund = wc_get_order( $refund_id );
        $refund_total_cost = 0;
        $order = wc_get_order( $args['order_id'] );
        if( $order ){
            $is_knawat_order = get_post_meta( $args['order_id'], '_knawat_order', true );
            if( '1' == $is_knawat_order ){
                update_post_meta( $refund_id, '_knawat_order', '1' );
            }
        }

        foreach ( $refund->get_items() as $refund_line_item_id => $refund_line_item ) {

            $item_id = $refund_line_item->get_id();
            $total = $refund_line_item->get_total();
            $qty = $refund_line_item->get_quantity();
            $refunded_item_id = wc_get_order_item_meta( $item_id, '_refunded_item_id', true );

            if( $total >= 0 || $qty ==  0 || empty( $refunded_item_id ) ){
                continue;
            }

			// get original item cost
			$item_cost = wc_get_order_item_meta( $refunded_item_id, '_knawat_item_cost', true );

			if ( ! $item_cost ) {
				continue;
			}

			// a refunded item cost & item total cost are negative since they reduce the item total costs when summed (for reports, etc)
			$refunded_item_cost       = $item_cost * -1;
			$refunded_item_total_cost = ( $item_cost * abs( $qty ) ) * -1;

			// add as meta to the refund line item
			wc_update_order_item_meta( $item_id, '_knawat_item_cost',       wc_format_decimal( $refunded_item_cost ) );
			wc_update_order_item_meta( $item_id, '_knawat_item_total_cost', wc_format_decimal( $refunded_item_total_cost ) );

			$refund_total_cost += $refunded_item_total_cost;
		}

		// update the refund total cost
		update_post_meta( $refund->get_id(), '_knawat_order_total_cost', wc_format_decimal( $refund_total_cost, wc_get_price_decimals() ) );
	}

     /**
     * Reduct local_ds stock.
     *
     * @param int $order_id
     * @param int $dropshipper
     */
    function knawat_dropshipwc_reduce_localds_stock( $order_id, $dropshipper ) {

        $order       = new WC_Order( $order_id );
        if( empty( $order ) ){ return; }
        $order_items = $order->get_items();

        foreach ( $order_items as $item ) {
            if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
                $variation_id = $item->get_variation_id();
                if( empty( $variation_id ) ){
                    $variation_id = $item->get_product_id();
                }

                $product_dropshipper = get_post_meta( $variation_id, '_knawat_dropshipper', true );
                if( $product_dropshipper == $dropshipper ){
                    $product_stock = get_post_meta( $variation_id, '_localds_stock', true );
                    $item_qty = $item->get_quantity();
                    if( $item_qty > 0 ){
                        update_post_meta( $variation_id, '_localds_stock', ( $product_stock - $item_qty ) );
                        // Add Stock Reduce note.
                        $item_name    = $item->get_name();
                        $dropshippers = knawat_dropshipwc_get_dropshippers();
                        $dropshipper_name = $dropshippers[$dropshipper]['name'];
                        $note         = sprintf( __( '%1$s stock reduced for "%2$s" from %3$s to %4$s.', 'dropshipping-woocommerce' ), $item_name, $dropshipper_name, $product_stock, ( $product_stock - $item_qty ) );
                        $order->add_order_note( $note );
                    }
                }
            }
        }
    }

	/**
	 * Add _knawat_order meta data if order contains knawat products.
	 * NOTE: This function is used only when Dokan Plugin is Active.
	 *
	 * @param  int $order_id
	 * @return void
	 */
	function knawat_dropshipwc_add_knawat_order_meta_data( $order_id ) {
		global $knawat_dropshipwc;
		if( $knawat_dropshipwc->common->is_knawat_order( $order_id ) ){
			update_post_meta( $order_id , '_knawat_order', 1 );
		}
	}
}
