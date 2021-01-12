<?php
/**
 * Knawat Admin Dashboard
 *
 * @category    Admin
 * @package     Knawat_Dropshipping_Woocommerce
 * @subpackage  Knawat_Dropshipping_Woocommerce/admin
 * @copyright   Copyright (c) 2018, Knawat
 * @since       1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Knawat_Dropshipping_Woocommerce_Admin_Dashboard', false ) ) :

	/**
	 * Knawat_Dropshipping_Woocommerce_Admin_Dashboard Class.
	 */
	class Knawat_Dropshipping_Woocommerce_Admin_Dashboard {

		/**
		 * Hook in tabs.
		 */
		public function __construct() {
			// Only hook in admin parts if the user has admin access
			if ( current_user_can( 'view_woocommerce_reports' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'publish_shop_orders' ) ) {
				add_action( 'wp_dashboard_setup', array( $this, 'init' ) );
			}
		}

		/**
		 * Init dashboard widgets.
		 */
		public function init() {
			wp_add_dashboard_widget( 'knawat_dropshipwc_dashboard_status', __( 'Knawat status', 'dropshipping-woocommerce' ), array( $this, 'status_widget' ) );
			wp_add_dashboard_widget(
				'knawat_latest_news_widget',
				esc_html__( 'Latest News from Knawat', 'dropshipping-woocommerce' ),
				array( $this, 'render_knawat_latest_news_widget' )
			);
		}

		/**
		 * Get Knawat top seller from Database.
		 *
		 * @return object
		 */
		private function get_knawat_top_seller() {
			global $wpdb;

			$query           = array();
			$query['fields'] = "SELECT SUM( order_item_meta.meta_value ) as qty, order_item_meta_2.meta_value as product_id
			FROM {$wpdb->posts} as posts";
			$query['join']   = "INNER JOIN {$wpdb->postmeta} AS order_meta ON posts.ID = order_meta.post_id AND order_meta.meta_key = '_knawat_order' ";
			$query['join']  .= "INNER JOIN {$wpdb->prefix}woocommerce_order_items AS order_items ON posts.ID = order_id ";
			$query['join']  .= "INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id ";
			$query['join']  .= "INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id ";

			$query['where']  = "WHERE posts.post_type IN ( '" . implode( "','", wc_get_order_types( 'order-count' ) ) . "' ) ";
			$query['where'] .= "AND posts.post_status IN ( 'wc-" . implode( "','wc-", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) ) ) . "' ) ";
			$query['where'] .= "AND order_item_meta.meta_key = '_qty' ";
			$query['where'] .= "AND order_item_meta_2.meta_key = '_product_id' ";
			$query['where'] .= "AND posts.post_date >= '" . date( 'Y-m-01', current_time( 'timestamp' ) ) . "' ";
			$query['where'] .= "AND posts.post_date <= '" . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) . "' ";
			$query['where'] .= "AND order_meta.meta_value = '1' ";

			$query['groupby'] = 'GROUP BY product_id';
			$query['orderby'] = 'ORDER BY qty DESC';
			$query['limits']  = 'LIMIT 1';
			return $wpdb->get_row( implode( ' ', apply_filters( 'woocommerce_dashboard_status_widget_knawat_top_seller_query', $query ) ) );

		}

		/**
		 * Get sales report data.
		 *
		 * @return object
		 */
		private function get_knawat_sales_report_data() {

			if ( defined( 'WC_PLUGIN_FILE' ) ) {
				include_once dirname( WC_PLUGIN_FILE ) . '/includes/admin/reports/class-wc-report-sales-by-date.php';

				/* Remove filter for remove sub orders from WooCommerce reports */
				global $knawat_dropshipwc;
				remove_filter( 'woocommerce_reports_get_order_report_query', array( $knawat_dropshipwc->orders, 'knawat_dropshipwc_admin_order_reports_remove_suborders' ) );
				/* remove dokan's filter for remove sub orders from WooCommerce reports*/
				if ( defined( 'DOKAN_PLUGIN_VERSION' ) ) {
					remove_filter( 'woocommerce_reports_get_order_report_query', 'dokan_admin_order_reports_remove_parents' );
				}
				add_filter( 'woocommerce_reports_get_order_report_data_args', array( $this, 'knawat_dropshipwc_reports_get_order_report_data_args' ) );

				$sales_by_date             = new WC_Report_Sales_By_Date();
				$sales_by_date->start_date = strtotime( date( 'Y-m-01', current_time( 'timestamp' ) ) );
				$sales_by_date->end_date   = current_time( 'timestamp' );
				// $sales_by_date->chart_groupby  = 'day';
				$sales_by_date->group_by_query = 'YEAR(posts.post_date), MONTH(posts.post_date), DAY(posts.post_date)';

				$report_data = $sales_by_date->get_report_data();

				remove_filter( 'woocommerce_reports_get_order_report_data_args', array( $this, 'knawat_dropshipwc_reports_get_order_report_data_args' ) );
				/* Remove sub orders from WooCommerce reports */
				add_filter( 'woocommerce_reports_get_order_report_query', array( $knawat_dropshipwc->orders, 'knawat_dropshipwc_admin_order_reports_remove_suborders' ) );
				if ( defined( 'DOKAN_PLUGIN_VERSION' ) ) {
					add_filter( 'woocommerce_reports_get_order_report_query', 'dokan_admin_order_reports_remove_parents' );
				}

				/**
				 * Count Total Knawat costs Over Knawat Orders.
				 */
				$total_refund_costs = 0;
				$refunded_orders    = array_merge( $report_data->partial_refunds, $report_data->full_refunds );
				foreach ( $refunded_orders as $key => $value ) {
					$total_refund_costs += floatval( $value->knawat_order_cost );
				}
				$total_costs              = wc_format_decimal( array_sum( wp_list_pluck( $report_data->orders, 'knawat_order_cost' ) ) + $total_refund_costs, 2 );
				$net_profit               = wc_format_decimal( $report_data->net_sales - $total_costs, 2 );
				$report_data->total_costs = $total_costs;
				$report_data->net_profit  = $net_profit;

				return $report_data;
			}
			return array();
		}

		/**
		 * Show status widget.
		 */
		public function status_widget() {

			$reports = array();
			if ( defined( 'WC_PLUGIN_FILE' ) ) {
				include_once dirname( WC_PLUGIN_FILE ) . '/includes/admin/reports/class-wc-admin-report.php';
				$reports = new WC_Admin_Report();
			}

			echo '<ul class="knawat_status_list">';
			$report_data = $this->get_knawat_sales_report_data();

			if ( current_user_can( 'view_woocommerce_reports' ) && ! empty( $report_data ) && isset( $report_data->net_sales ) ) {
				?>
			<li class="sales-this-month">
				<a href="#">
					<?php
						/* translators: %s: net sales */
						printf(
							__( '%s net sales this month', 'dropshipping-woocommerce' ),
							'<strong>' . wc_price( $report_data->net_sales ) . '</strong>'
						);
					?>
				</a>
			</li>
				<?php
			}

			if ( current_user_can( 'view_woocommerce_reports' ) && ! empty( $report_data ) && isset( $report_data->net_profit ) ) {
				?>
			<li class="profit-this-month">
				<a href="#">
					<?php
						/* translators: %s: net profit */
						printf(
							__( '%s net profit this month', 'dropshipping-woocommerce' ),
							'<strong>' . wc_price( $report_data->net_profit ) . '</strong>'
						);
					?>
				</a>
			</li>
				<?php
			}

			if ( current_user_can( 'view_woocommerce_reports' ) && ( $top_seller = $this->get_knawat_top_seller() ) && $top_seller->qty ) {
				?>
			<li class="best-seller-this-month">
				<a href="<?php echo admin_url( 'admin.php?page=wc-reports&tab=orders&report=sales_by_product&range=month&product_ids=' . $top_seller->product_id ); ?>">
					<?php
					if ( ! empty( $reports ) ) {
						echo $reports->sales_sparkline( $top_seller->product_id, max( 7, date( 'd', current_time( 'timestamp' ) ) ), 'count' );
					}
					?>
					<?php
						/* translators: 1: top seller product title 2: top seller quantity */
						printf(
							__( '%1$s top seller this month (sold %2$d)', 'dropshipping-woocommerce' ),
							'<strong>' . get_the_title( $top_seller->product_id ) . '</strong>',
							$top_seller->qty
						);
					?>
				</a>
			</li>
				<?php
			}

			$this->status_widget_order_rows();
			$this->status_widget_stock_rows();

			do_action( 'knawat_dropshipwc_after_dashboard_status_widget' );
			echo '</ul>';

		}

		/**
		 * Show order data is status widget.
		 */
		private function status_widget_order_rows() {
			global $wpdb;
			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				return;
			}
			$on_hold_count    = 0;
			$processing_count = 0;

			$query = "SELECT posts.post_status, COUNT( DISTINCT posts.ID ) as count FROM {$wpdb->posts} as posts
		INNER JOIN {$wpdb->postmeta} as order_meta ON (  posts.ID = order_meta.post_id AND order_meta.meta_key = '_knawat_order' )
		AND posts.post_type = 'shop_order'
		AND ( posts.post_status IN ( 'wc-on-hold', 'wc-processing' ) )
		AND order_meta.meta_value = '1'
		GROUP BY posts.post_status";

			$counts = $wpdb->get_results( $query );
			if ( ! empty( $counts ) ) {
				foreach ( $counts as $count ) {
					if ( isset( $count->post_status ) && $count->post_status == 'wc-on-hold' ) {
						$on_hold_count += isset( $count->count ) ? $count->count : 0;
					}
					if ( isset( $count->post_status ) && $count->post_status == 'wc-processing' ) {
						$processing_count += isset( $count->count ) ? $count->count : 0;
					}
				}
			}
			?>
		<li class="processing-orders">
			<a href="#">
				<?php
					/* translators: %s: order count */
					printf(
						_n( '<strong>%s order</strong> awaiting processing', '<strong>%s orders</strong> awaiting processing', $processing_count, 'dropshipping-woocommerce' ),
						$processing_count
					);
				?>
			</a>
		</li>
		<li class="on-hold-orders">
			<a href="#">
				<?php
					/* translators: %s: order count */
					printf(
						_n( '<strong>%s order</strong> on-hold', '<strong>%s orders</strong> on-hold', $on_hold_count, 'dropshipping-woocommerce' ),
						$on_hold_count
					);
				?>
			</a>
		</li>
			<?php
		}

		/**
		 * Show stock data is status widget.
		 */
		private function status_widget_stock_rows() {
			global $wpdb;

			// Get products using a query - this is too advanced for get_posts :(
			$stock   = absint( max( get_option( 'woocommerce_notify_low_stock_amount' ), 1 ) );
			$nostock = absint( max( get_option( 'woocommerce_notify_no_stock_amount' ), 0 ) );

			$transient_name = 'wc_knawat_low_stock_count';
			if ( false === ( $lowinstock_count = get_transient( $transient_name ) ) ) {
				$query_from       = apply_filters(
					'woocommerce_report_low_in_stock_query_from',
					"FROM {$wpdb->posts} as posts
				INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
				INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
				INNER JOIN {$wpdb->postmeta} AS postmeta3 ON posts.ID = postmeta3.post_id
				WHERE 1=1
				AND posts.post_type IN ( 'product', 'product_variation' )
				AND posts.post_status = 'publish'
				AND postmeta2.meta_key = '_manage_stock' AND postmeta2.meta_value = 'yes'
				AND postmeta3.meta_key = 'dropshipping' AND postmeta3.meta_value = 'knawat'
				AND postmeta.meta_key = '_stock' AND CAST(postmeta.meta_value AS SIGNED) <= '{$stock}'
				AND postmeta.meta_key = '_stock' AND CAST(postmeta.meta_value AS SIGNED) > '{$nostock}'
			"
				);
				$lowinstock_count = absint( $wpdb->get_var( "SELECT COUNT( DISTINCT posts.ID ) {$query_from};" ) );
				set_transient( $transient_name, $lowinstock_count, DAY_IN_SECONDS / 24 );
			}

			$transient_name = 'wc_knawat_outofstock_count';
			if ( false === ( $outofstock_count = get_transient( $transient_name ) ) ) {
				$query_from       = apply_filters(
					'woocommerce_report_out_of_stock_query_from',
					"FROM {$wpdb->posts} as posts
				INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
				INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
				INNER JOIN {$wpdb->postmeta} AS postmeta3 ON posts.ID = postmeta3.post_id
				WHERE 1=1
				AND posts.post_type IN ( 'product', 'product_variation' )
				AND posts.post_status = 'publish'
				AND postmeta2.meta_key = '_manage_stock' AND postmeta2.meta_value = 'yes'
				AND postmeta3.meta_key = 'dropshipping' AND postmeta3.meta_value = 'knawat'
				AND postmeta.meta_key = '_stock' AND CAST(postmeta.meta_value AS SIGNED) <= '{$nostock}'
			"
				);
				$outofstock_count = absint( $wpdb->get_var( "SELECT COUNT( DISTINCT posts.ID ) {$query_from};" ) );
				set_transient( $transient_name, $outofstock_count, DAY_IN_SECONDS / 24 );
			}
			?>
		<li class="low-in-stock">
			<a href="#">
				<?php
					/* translators: %s: order count */
					printf(
						_n( '<strong>%s product</strong> low in stock', '<strong>%s products</strong> low in stock', $lowinstock_count, 'dropshipping-woocommerce' ),
						$lowinstock_count
					);
				?>
			</a>
		</li>
		<li class="out-of-stock">
			<a href="#">
				<?php
					/* translators: %s: order count */
					printf(
						_n( '<strong>%s product</strong> out of stock', '<strong>%s products</strong> out of stock', $outofstock_count, 'dropshipping-woocommerce' ),
						$outofstock_count
					);
				?>
			</a>
		</li>
			<?php
		}

		/**
		 * Add data for filter knawat orders only.
		 *
		 * @param   array $args args for get report data.
		 * @return  array $args Altered args.
		 */
		public function knawat_dropshipwc_reports_get_order_report_data_args( $args ) {
			if ( ! empty( $args ) ) {
				$args['data']['_knawat_order'] = array(
					'type'     => 'meta',
					'function' => '',
					'name'     => 'knawat_order',
				);

				if ( isset( $args['data']['_order_total'] ) && isset( $args['data']['_order_total']['function'] ) ) {
					$args['data']['_knawat_order_total_cost'] = array(
						'type'     => 'meta',
						'function' => $args['data']['_order_total']['function'],
						'name'     => 'knawat_order_cost',
					);
				}
			}
			return $args;
		}

		/**
		 * Render the Knawat Latest New Widget.
		 *
		 * @access public
		 * @return void
		 */
		public function render_knawat_latest_news_widget() {
			echo '<div class="knawat-latest-news-widget">';
			wp_widget_rss_output(
				'https://knawat.com/feed/',
				array(
					'items'        => 5,
					'show_summary' => 1,
					'show_date'    => 1,
				)
			);
			echo '</div>';
		}

	}

endif;

add_action( 'admin_init', 'knawat_dropshipwc_load_admin_dashboard' );
function knawat_dropshipwc_load_admin_dashboard() {
	return new Knawat_Dropshipping_Woocommerce_Admin_Dashboard();
}
