<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package     Knawat_Dropshipping_Woocommerce
 * @subpackage  Knawat_Dropshipping_Woocommerce/admin
 * @copyright   Copyright (c) 2018, Knawat
 * @since       1.0.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package     Knawat_Dropshipping_Woocommerce
 * @subpackage  Knawat_Dropshipping_Woocommerce/admin
 * @author     Dharmesh Patel <dspatel44@gmail.com>
 */
class Knawat_Dropshipping_Woocommerce_Admin {


	public $adminpage_url;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->adminpage_url = admin_url('admin.php?page=knawat_dropship' );

		add_action( 'admin_menu', array( $this, 'add_menu_pages') );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts') );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles') );
		add_action( 'after_setup_theme', array( $this, 'knawat_setup_wizard' ) );
		add_filter( 'views_edit-product', array( $this, 'knawat_dropshipwc_add_new_product_filter' ) );
		add_filter( 'views_edit-shop_order', array( $this, 'knawat_dropshipwc_add_new_order_filter' ) );
		add_action( 'load-edit.php', array( $this, 'knawat_dropshipwc_load_custom_knawat_filter' ) );
		add_action( 'load-edit.php', array( $this, 'knawat_dropshipwc_load_custom_knawat_order_filter' ) );
		add_filter( 'admin_footer_text', array( $this, 'add_dropshipping_woocommerce_credit' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'knawat_dropshipwc_add_knawat_order_status_in_backend' ), 10 );
		add_action( 'admin_init', array( $this, 'add_default_category_notice' ) );

		// Display admin notices.
		add_action( 'admin_notices', array( $this, 'display_notices') );
		// Start Manual import.
		add_action( 'admin_post_knawatds_manual_import', array( $this, 'knawat_start_manual_product_import') );
		// Stop import.
		add_action( 'admin_post_knawatds_stop_import', array( $this, 'knawat_stop_product_import') );

		// Add Knawat Order Status column to order list table
		add_filter( 'manage_shop_order_posts_columns', array( $this, 'knawat_dropshipwc_shop_order_columns' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'knawat_dropshipwc_render_shop_order_columns' ) );

		// Display Knawat Cost in Variation
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'knawat_dropshipwc_add_knawat_cost_field' ), 10, 3 );

		// Add & Save Product Variation Dropshipper and Quantity
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'knawat_dropshipwc_add_dropshipper_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'knawat_dropshipwc_save_dropshipper_field' ), 10, 2 );

		// Handle Cron Schedule for existing users.
		add_action( 'admin_init', array( $this, 'knawat_dropshipwc_maybe_update' ) );

		// Pull Order information from knawat.com
		add_action( 'current_screen', array( $this, 'knawat_dropshipwc_update_knawat_order' ), 99 );

		// Sync Single and product list from knawat
		add_action('add_meta_boxes_product', array( $this, 'knawat_dropshipwc_add_update_metabox' ) );
		add_action( 'admin_post_update_knawat_product', array( $this, 'knawat_dropshipwc_update_product' ), 99 );
		add_filter( 'bulk_actions-edit-product', array($this, 'knawat_dropshipwc_bulk_actions'), 99);
		add_filter( 'handle_bulk_actions-edit-product', array($this, 'knawat_dropshipwc_update_bulk_products'), 10, 3);
	}

	/**
	 * Create the Admin menu and submenu and assign their links to global varibles.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function add_menu_pages() {

		add_menu_page( __( 'Knawat Dropshipping', 'dropshipping-woocommerce' ), __( 'Dropshipping', 'dropshipping-woocommerce' ), 'manage_options', 'knawat_dropship', array( $this, 'admin_page' ), KNAWAT_DROPWC_PLUGIN_URL . 'assets/images/knawat.png', '30' );
	}

	/**
	 * Include require libraries & config for knawat setup wizard.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function knawat_setup_wizard() {
		require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/knawat-merlin-config.php';
	}

	/**
	 * Load Admin page.
	 *
	 * @since 1.0
	 * @return void
	 */
	function admin_page() {

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Knawat Dropshipping', 'dropshipping-woocommerce' ); ?></h1>
			<?php
			// Set Default Tab to Import.
			$tab = isset( $_GET[ 'tab' ] ) ? sanitize_text_field( $_GET[ 'tab' ] ) : 'import';
			$consumer_keys = knawat_dropshipwc_get_consumerkeys();
			if( empty( $consumer_keys ) ){
				$tab = isset( $_GET[ 'tab' ] ) ? sanitize_text_field( $_GET[ 'tab' ] ) : 'settings';
			}
			?>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">

					<div id="postbox-container-1" class="postbox-container">
						<?php //require_once KNAWAT_DROPWC_PLUGIN_DIR . '/templates/admin-sidebar.php'; ?>
					</div>
					<div id="postbox-container-2" class="postbox-container">

						<h1 class="nav-tab-wrapper">
							<a href="<?php echo esc_url( add_query_arg( 'tab', 'import', $this->adminpage_url ) ); ?>" class="nav-tab <?php if ( $tab == 'import' ) { echo 'nav-tab-active'; } ?>">
								<?php esc_html_e( 'Product Import', 'dropshipping-woocommerce' ); ?>
							</a>

							<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $this->adminpage_url ) ); ?>" class="nav-tab <?php if ( $tab == 'settings' ) { echo 'nav-tab-active'; } ?>">
								<?php esc_html_e( 'Settings', 'dropshipping-woocommerce' ); ?>
							</a>
						</h1>

						<div class="dropshipping-woocommerce-page">
							<?php
							if ( 'import' === $tab ) {

								require_once KNAWAT_DROPWC_PLUGIN_DIR . '/templates/admin/dropshipping-woocommerce-import.php';

							} elseif ( 'settings' === $tab ) {

								require_once KNAWAT_DROPWC_PLUGIN_DIR . '/templates/admin/dropshipping-woocommerce-settings.php';

							}
							?>
							<div style="clear: both"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add Knawat Orders Filter view at filters
	 *
	 * @since 1.0
	 * @param  array $views Array of filter views
	 * @return array $views Array of filter views
	 */
	function knawat_dropshipwc_add_new_order_filter( $views ){

		global $wpdb;

		$t_order_items = $wpdb->prefix . "woocommerce_order_items";
		$t_order_itemmeta = $wpdb->prefix . "woocommerce_order_itemmeta";

		$count_query = "SELECT COUNT( DISTINCT {$wpdb->posts}.ID ) as count FROM {$wpdb->posts}
			INNER JOIN {$wpdb->postmeta} as pm ON {$wpdb->posts}.ID = pm.post_id AND pm.meta_key = '_knawat_order'
			WHERE 1=1 AND {$wpdb->posts}.post_type = 'shop_order'
			AND ( {$wpdb->posts}.post_status != 'wc-cancelled' AND {$wpdb->posts}.post_status != 'trash' )
			AND pm.meta_value = 1";

		$count = $wpdb->get_var( $count_query );

		if( $count > 0 ){
			$class = '';
			if ( isset( $_GET[ 'knawat_orders' ] ) && !empty( $_GET[ 'knawat_orders' ] ) ){
				$class = 'current';
			}

			$views_html = sprintf( "<a class='%s' href='edit.php?post_type=shop_order&knawat_orders=1'>%s</a><span class='count'>(%d)</span>", $class, __('Knawat Orders', 'dropshipping-woocommerce' ), $count );
			$views['knawat'] = $views_html;
		}
		// Removed Mine filter from order listing screen.
		if( isset( $views['mine'] ) ){
			unset( $views['mine'] );
		}
		return $views;
	}

	/**
	 * Add `posts_where` filter if knawat orders need to filter
	 *
	 * @since 1.0
	 * @return void
	 */
	function knawat_dropshipwc_load_custom_knawat_order_filter(){
	    global $typenow;
	    if( 'shop_order' != $typenow ){
	        return;
	    }

	    if ( isset( $_GET[ 'knawat_orders' ] ) && !empty( $_GET[ 'knawat_orders' ] ) && trim( $_GET[ 'knawat_orders' ] ) == 1 ){
			add_filter( 'posts_where' , array( $this, 'knawat_dropshipwc_posts_where_knawat_orders') );
			add_filter( 'posts_join', array( $this, 'knawat_dropshipwc_posts_join_knawat_orders') );
	    }
	}

	/**
	 * Add condtion in WHERE statement for filter only knawat orders in orders list table
	 *
	 * @since  1.0
	 * @param  string $where Where condition of SQL statement for orders query
	 * @return string $where Modified Where condition of SQL statement for orders query
	 */
	function knawat_dropshipwc_posts_where_knawat_orders( $where ){
	    global $wpdb;

		if ( isset( $_GET[ 'knawat_orders' ] ) && !empty( $_GET[ 'knawat_orders' ] ) && trim( $_GET[ 'knawat_orders' ] ) == 1 ){
	        $where .= " AND ( {$wpdb->posts}.post_status != 'wc-cancelled' AND {$wpdb->posts}.post_status != 'trash' ) AND {$wpdb->postmeta}.meta_value = 1 ";
	    }
	    return $where;
	}

	/**
	 * Add JOIN statement for filter only knawat orders in orders list table
	 *
	 * @since  1.0
	 * @param  string $join join of SQL statement for orders query
	 * @return string $join Modified join of SQL statement for orders query
	 */
	function knawat_dropshipwc_posts_join_knawat_orders( $join ){
	    global $wpdb;

	    if ( isset( $_GET[ 'knawat_orders' ] ) && !empty( $_GET[ 'knawat_orders' ] ) && trim( $_GET[ 'knawat_orders' ] ) == 1 ){
			$join .= "INNER JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_knawat_order'";
	    }
	    return $join;
	}


	/**
	 * Add Knawat Product Filter view at filters
	 *
	 * @since 1.0
	 * @param  array $views Array of filter views
	 * @return array $views Array of filter views
	 */
	function knawat_dropshipwc_add_new_product_filter( $views ){

		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT( DISTINCT p.ID) as count FROM {$wpdb->posts} as p INNER JOIN {$wpdb->postmeta} as pm ON ( p.ID = pm.post_id ) WHERE 1=1 AND ( pm.meta_key = 'dropshipping' AND pm.meta_value = 'knawat' ) AND p.post_type = 'product' AND p.post_status != 'trash'" );
		if( $count > 0 ){
			$class = '';
			if ( isset( $_GET[ 'knawat_products' ] ) && !empty( $_GET[ 'knawat_products' ] ) ){
				$class = 'current';
			}

			$views_html = sprintf( "<a class='%s' href='edit.php?post_type=product&knawat_products=1'>%s</a><span class='count'>(%d)</span>", $class, __('Knawat Products', 'dropshipping-woocommerce' ), $count );
			$views['knawat'] = $views_html;
		}
		return $views;
	}

	/**
	 * Add `posts_where` filter if knawat products need to filter
	 *
	 * @since 1.0
	 * @return void
	 */
	function knawat_dropshipwc_load_custom_knawat_filter(){
	    global $typenow;
	    if( 'product' != $typenow ){
	        return;
	    }

	    if ( isset( $_GET[ 'knawat_products' ] ) && !empty( $_GET[ 'knawat_products' ] ) && trim( $_GET[ 'knawat_products' ] ) == 1 ){
	    	add_filter( 'posts_where' , array( $this, 'knawat_dropshipwc_posts_where_knawat_products') );
	    }
	}

	/**
	 * Add condtion in WHERE statement for filter only knawat products in products list table
	 *
	 * @since  1.0
	 * @param  string $where Where condition of SQL statement for products query
	 * @return string $where Modified Where condition of SQL statement for products query
	 */
	function knawat_dropshipwc_posts_where_knawat_products( $where ){
	    global $wpdb;
	    if ( isset( $_GET[ 'knawat_products' ] ) && !empty( $_GET[ 'knawat_products' ] ) && trim( $_GET[ 'knawat_products' ] ) == 1 ){
	        $where .= " AND ID IN ( SELECT post_id FROM $wpdb->postmeta WHERE meta_key='dropshipping' AND meta_value='knawat' )";
	    }
	    return $where;
	}


	/**
	 * Add Knawat Dropshipping Woocommerce ratting text in wp-admin footer
	 *
	 * @since 1.0
	 * @return void
	 */
	public function add_dropshipping_woocommerce_credit( $footer_text ){
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( $page != '' && $page == 'knawat_dropship' ) {
			$rate_url = 'https://wordpress.org/support/plugin/dropshipping-woocommerce/reviews/?rate=5#new-post';

			$footer_text .= sprintf(
				esc_html__( ' Rate %1$s Dropshipping Woocommerce%2$s %3$s', 'dropshipping-woocommerce' ),
				'<strong>',
				'</strong>',
				'<a href="' . $rate_url . '" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}
		return $footer_text;
	}

	/**
	 * Load Admin Scripts
	 *
	 * Enqueues the required admin scripts.
	 *
	 * @since 1.1.0
	 * @param string $hook Page hook
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		$js_dir  = KNAWAT_DROPWC_PLUGIN_URL . 'assets/js/';
		wp_register_script( 'dropshipping-woocommerce', $js_dir . 'dropshipping-woocommerce-admin.js', array('jquery' ), KNAWAT_DROPWC_VERSION );
		$params = array(
			'nonce' => wp_create_nonce('kdropshipping_nonce'),
		);
		wp_localize_script( 'dropshipping-woocommerce', 'kdropshipping_object', $params );
		wp_enqueue_script( 'dropshipping-woocommerce' );

	}

	/**
	 * Load Admin Styles.
	 *
	 * Enqueues the required admin styles.
	 *
	 * @since 1.1.0
	 * @param string $hook Page hook
	 * @return void
	 */
	public function enqueue_admin_styles( $hook ) {
		$css_dir  = KNAWAT_DROPWC_PLUGIN_URL . 'assets/css/';
		wp_enqueue_style('dropshipping-woocommerce', $css_dir . 'dropshipping-woocommerce-admin.css', false, "" );
	}

	/**
	 * Display notices in admin.
	 *
	 * @since    2.0.0
	 */
	public function display_notices() {
		global $knawatdswc_errors, $knawatdswc_success, $knawatdswc_warnings;

		if ( ! empty( $knawatdswc_errors ) ) {
			foreach ( $knawatdswc_errors as $error ) :
			    ?>
			    <div class="notice notice-error is-dismissible">
			        <p><?php echo $error; ?></p>
			    </div>
			    <?php
			endforeach;
		}

		if ( ! empty( $knawatdswc_success ) ) {
			foreach ( $knawatdswc_success as $success ) :
			    ?>
			    <div class="notice notice-success is-dismissible">
			        <p><?php echo $success; ?></p>
			    </div>
			    <?php
			endforeach;
		}

		if ( ! empty( $knawatdswc_warnings ) ) {
			foreach ( $knawatdswc_warnings as $warning ) :
			    ?>
			    <div class="notice notice-warning is-dismissible">
			        <p><?php echo $warning; ?></p>
			    </div>
			    <?php
			endforeach;
		}

		if( isset( $_GET['manual_run']) && isset( $_GET['tab'] ) && '1' === $_GET['manual_run'] && 'import' === $_GET['tab'] ){
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Manual import started successfully.','dropshipping-woocommerce' ); ?></p>
			</div>
			<?php
		}

		if( isset( $_GET['manual_run']) && isset( $_GET['tab'] ) && '0' === $_GET['manual_run'] && 'import' === $_GET['tab'] ){
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php _e( 'Something went wrong during start manual import.','dropshipping-woocommerce' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['order_sync'] ) ) {
			if ( '1' === $_GET['order_sync'] ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_attr_e( 'Order(s) has been synchronized successfully.','dropshipping-woocommerce' ); ?></p>
				</div>
				<?php
			} else {
				?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_attr_e( 'Something went wrong during order synchronization, please try again.','dropshipping-woocommerce' ); ?></p>
				</div>
				<?php
			}
		}

		if ( isset( $_GET['knawat_update_done'] ) && !empty( $_GET['knawat_update_done'] ) ) {
			$update_count = sanitize_text_field($_GET['knawat_update_done']);
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_attr_e( "{$update_count} Knawat product(s) has been updated successfully.","dropshipping-woocommerce" ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['knawat_product_sync'] ) && !empty( $_GET['knawat_product_sync'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_attr_e( "Knawat product has been updated successfully.","dropshipping-woocommerce" ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Manually Start product import.
	 *
	 * @since    2.0.0
	 */
	public function knawat_start_manual_product_import() {
		if( wp_verify_nonce( $_GET['manual_nonce'], 'knawatds_manual_import_action') ){
			global $knawatdswc_errors;
			do_action( 'knawat_dropshipwc_run_product_import', array('force_full_import' => 1) );

			if( empty( $knawatdswc_errors ) ){
				$redirect_url = esc_url_raw( add_query_arg( array( 'tab' => 'import', 'manual_run' => '1' ), $this->adminpage_url ) );
				wp_redirect(  $redirect_url );
				exit();
			}
		}
		$redirect_url = esc_url_raw( add_query_arg( array( 'tab' => 'import', 'manual_run' => '0' ), $this->adminpage_url ) );
		wp_redirect(  $redirect_url );
		exit();
	}

	/**
	 * Stop product import.
	 *
	 * @since    2.0.0
	 */
	public function knawat_stop_product_import() {
		if( wp_verify_nonce( sanitize_key( $_GET['stop_import_nonce'] ), 'knawatds_stop_import_action') ){

			if ( class_exists( 'Knawat_Dropshipping_WC_Background', false ) ) {
				$import_process = new Knawat_Dropshipping_WC_Background();
				// Kill Import
				$import_process->kill_process();
				set_transient( 'knawat_stop_import', 'product_import', 20 );
			}
			$messages = array();
			$messages['success'][] = esc_attr__( 'Import has been stopped successfully.', 'dropshipping-woocommerce' );
			knawat_set_notices($messages);
		}
		$redirect_url = esc_url_raw( add_query_arg( array( 'tab' => 'import' ), $this->adminpage_url ) );
		wp_safe_redirect(  $redirect_url );
		exit();
	}

	/**
	 * Display Knawat Order Status in Order Meta Box
	 *
	 * @since 1.1.0
	 * @param object $order Order
	 * @return void
	 */
	public function knawat_dropshipwc_add_knawat_order_status_in_backend( $order ){
		global $knawat_dropshipwc;
		$order_id = $order->get_id();
		if( !$knawat_dropshipwc->common->is_knawat_order( $order_id ) ){
			return;
		}
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$knawat_order_status = get_post_meta( $order_id, '_knawat_order_status', true );
		} else {
			$knawat_order_status = $order->get_meta( '_knawat_order_status', true );
		}
		if( $knawat_order_status != '' ){
			?>
			<p class="form-field" style="color: #000">
				<strong><?php _e( 'Knawat Order Status:', 'dropshipping-woocommerce' ); ?></strong><br/>
				<span><?php echo ucfirst( $knawat_order_status ); ?></span>
			</p>
			<?php
		}
	}

	/**
	 * Define Knawat Order Status column in admin orders list.
	 *
	 * @since 1.1.0
	 * @param 	array $columns Existing columns
	 * @return 	array modilfied columns
	 */
	public function knawat_dropshipwc_shop_order_columns( $columns ){
		$columns['knawat_status'] = __( 'Knawat Status', 'dropshipping-woocommerce' );
		return $columns;
	}


	/**
	 * Render Knawat Order Status in custom column
	 *
	 * @since 1.1.0
	 * @param 	string $column Current column
	 */
	public function knawat_dropshipwc_render_shop_order_columns( $column ){
		global $post;
		if ( 'knawat_status' === $column ) {
			$order_id = $post->ID;
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$knawat_order_status = get_post_meta( $order_id, '_knawat_order_status', true );
			} else {
				$order = new WC_Order( $order_id );
				$knawat_order_status = $order->get_meta( '_knawat_order_status', true );
			}

			if( $knawat_order_status != '' ){
				if ( version_compare( WC_VERSION, '3.3', '>=' ) ) {
					?>
					<mark class="order-status"><span><?php echo ucfirst( $knawat_order_status ); ?></span></mark>
					<?php
				}else{
					?>
					<span class="knawat-order-status"><?php echo ucfirst( $knawat_order_status ); ?></span>
					<?php
				}
			}else{
				echo '–';
			}
		}
	}


	/**
	 * Render Read-Only Knawat Cost in Variation Prices block
	 *
	 * @since 1.2.0
	 *
	 * @param int     $loop
	 * @param array   $variation_data
	 * @param WP_Post $variation
	 */
	public function knawat_dropshipwc_add_knawat_cost_field( $loop, $variation_data, $variation ){
		$knawat_cost = get_post_meta( $variation->ID, '_knawat_cost', true );
		if( !empty( $knawat_cost ) ){
			$label = __( 'Knawat Cost ($)', 'dropshipping-woocommerce' );
			?>
			<p class="form-field knawat_dropshipwc_knawat_cost form-row form-row-first">
				<label for="knawat_cost<?php echo $loop; ?>"><?php echo $label; ?></label>
				<input class="short knawat_cost" id="knawat_cost<?php echo $loop; ?>" value="<?php echo $knawat_cost; ?>" placeholder="<?php echo $label; ?>" type="text" disabled="disabled">
			</p>
			<?php
		}
	}


	/**
	 * Render Dropshipper and Qty field in Variation Attribute block
	 *
	 * @since 1.2.0
	 *
	 * @param int     $loop
	 * @param array   $variation_data
	 * @param WP_Post $variation
	 */
	public function knawat_dropshipwc_add_dropshipper_field( $loop, $variation_data, $variation ){
		global $knawat_dropshipwc;
		$dropshippers_temp = $knawat_dropshipwc->get_dropshippers();
		if ( empty( $dropshippers_temp ) ) {
			return;
		}
		$dropshippers = array();
		foreach ( $dropshippers_temp as $dropship ) {
			$dropshippers[$dropship["id"]] = $dropship["name"];
		}

		$dropshipper = 'default';
		$localds_stock = 0;

		if( isset( $variation_data['_knawat_dropshipper'][0] ) ){
			$dropshipper = $variation_data['_knawat_dropshipper'][0];
			if( empty( $dropshipper ) ){
				$dropshipper = 'default';
			}
		}
		if( isset( $variation_data['_localds_stock'][0] ) ){
			$localds_stock = $variation_data['_localds_stock'][0];
			if( empty( $localds_stock ) ){
				$localds_stock = 0;
			}
		}
		?>

		<div id="knawat_dropshipwc_dropshipper_<?php echo $variation->ID; ?>" class="knawat_dropshipwc_dropshipper_wrap">
			<?php
			woocommerce_wp_select( array(
				'id'            => "knawat_dropshipper{$variation->ID}",
				'name'          => "knawat_dropshipper[{$variation->ID}]",
				'class'			=> 'knawat_dropshipper_select',
				'value'         => $dropshipper,
				'label'         => __('Dropshipper', 'dropshipping-woocommerce' ),
				'options'       => $dropshippers,
				'desc_tip'      => true,
				'description'   => __( 'Select Dropshipper for this Product variantion.', 'dropshipping-woocommerce' ),
				'wrapper_class' => 'knawat_dropshipwc_dropshipper form-row form-row-first',
			) );

			woocommerce_wp_text_input( array(
				'id'                => "localds_stock{$variation->ID}",
				'name'              => "localds_stock[{$variation->ID}]",
				'value'             => $localds_stock,
				'label'             => __( 'Stock quantity (Dropshipper)', 'dropshipping-woocommerce' ),
				'desc_tip'          => true,
				'description'       => __( "Enter a quantity of product variant for selected dropshipper.", 'dropshipping-woocommerce' ),
				'type'              => 'number',
				'wrapper_class'     => 'knawat_dropshipwc_localds_stock form-row form-row-last',
			) );
			?>
		</div>
		<?php
	}

	/**
	 * Save Dropshipper and dropshipper Qty for Product variation
	 *
	 * @return void
	 */
	public function knawat_dropshipwc_save_dropshipper_field( $variation_id, $i ){

		if( isset( $_POST['knawat_dropshipper'][$variation_id] ) ){
			$dropshipper = isset( $_POST['knawat_dropshipper'][$variation_id] ) ? sanitize_text_field( $_POST['knawat_dropshipper'][$variation_id] ) : 'default';
			update_post_meta( $variation_id, '_knawat_dropshipper', $dropshipper );
		}

		if( isset( $_POST['localds_stock'][$variation_id] ) ){
			$localds_stock = isset( $_POST['localds_stock'][$variation_id] ) ? absint( $_POST['localds_stock'][$variation_id] ) : 0;
			update_post_meta( $variation_id, '_localds_stock', $localds_stock );
		}
	}

	/**
	 * Check if update actions needed and perform if needed
	 *
	 * @return void
	 */
	public function knawat_dropshipwc_maybe_update(){
		$installed_version = get_option( 'knawat_dropwc_version', '1.2.0' );

		if ( version_compare( $installed_version, KNAWAT_DROPWC_VERSION, '<' ) ) {
			if ( version_compare( $installed_version, '2.0.0', '<' ) ) {
				if( !wp_next_scheduled( 'knawat_dropshipwc_run_product_import' ) ) {
					// Add Hourly Scheduled import.
					wp_schedule_event( time(), 'hourly', 'knawat_dropshipwc_run_product_import' );
				}
				// Delete Deprecated webhooks.
				knawat_dropshipwc_delete_deprecated_webhooks();
				// Delete Deprecated API Keys.
				knawat_dropshipwc_delete_deprecated_api_keys();
			}
			update_option( 'knawat_dropwc_version', KNAWAT_DROPWC_VERSION );
		}
	}

	/**
	 * Set notice on Settings page for select default WooCommerce Category.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function add_default_category_notice() {
		global $knawat_dropshipwc, $knawatdswc_warnings;
		if ( isset( $_GET[ 'page' ] ) && 'knawat_dropship' === sanitize_text_field( $_GET[ 'page' ] ) && isset( $_GET[ 'tab' ] ) && 'settings' === sanitize_text_field( $_GET[ 'tab' ] ) ) {
			if( $knawat_dropshipwc->common->is_admin_notice_active('select_default_cat') ){
				$knawatdswc_warnings[] = sprintf( '%s <a href="#" class="knawat_dismiss_notice" data-noticetype="select_default_cat"> %s</a>', __( 'Before you start importing products, it\'s better to set default category for import Knawat Products. You can set it from <strong>Products > Categories.</strong>  ex: New arrivals, or something else as you want.', 'dropshipping-woocommerce' ), __('Dismiss this notice.', 'dropshipping-woocommerce' ));
			}
		}
	}

	/**
	 * Pull knawat order information from Knawat.com
	 *
	 * @since 2.0
	 * @return void
	 */
	public function knawat_dropshipwc_update_knawat_order( $current_screen = '' ){
		if( $current_screen->base == 'post' && $current_screen->id == 'shop_order' ){
			$order_id = 0;
			if ( isset( $_GET['post'] ) ) {
				$order_id = (int) $_GET['post'];
			} elseif ( isset( $_POST['post_ID'] ) ) {
				$order_id = (int) $_POST['post_ID'];
			}

			if( $order_id > 0 ){
				$order = wc_get_order( $order_id );
				if ( empty( $order ) ) {
					return;
				}
				$is_knawat = get_post_meta( $order_id, '_knawat_order', true );
				$knawat_order_id = get_post_meta( $order_id, '_knawat_order_id', true );
				if ( 1 == $is_knawat && $knawat_order_id != '' ) {
					if ( ! class_exists( 'Knawat_Dropshipping_WC_Async_Request', false ) ) {
						return;
					}
					// Async Order Update.
					$async_request = new Knawat_Dropshipping_WC_Async_Request();
					$async_request->data( array( 'operation' => 'pull_order', 'knawat_order_id' => $knawat_order_id, 'order_id' => $order_id ) );
					$temp = $async_request->dispatch();
				}
			}
		} elseif( $current_screen->base == 'edit' && $current_screen->id == 'edit-shop_order'  ){
			// Fire Pull Order hook.
			do_action( 'knawat_dropshipwc_run_pull_orders' );
		}
	}

	/**
	 * Add update product metabox in product edit page
	 *
	 * @return void.
	 */
	public function knawat_dropshipwc_add_update_metabox(){
		global $post;
		if (knawat_dropshipwc_is_knawat_product($post->ID)){
			add_meta_box('knawatproduct_metabox',
				__('Knawat Dropshipping', 'dropshipping-woocommerce'),
				array($this, 'knawat_dropshipwc_render_update_metabox'),
				'product',
				'side',
				'high'
			);
		}
	}

	/**
	 * Render metabox for update knawat product.
	 */
	public function knawat_dropshipwc_render_update_metabox($post){
		$url = wp_nonce_url(admin_url('admin-post.php?action=update_knawat_product&product_id='.$post->ID), 'update_knawat_product_action', 'update_knawat_product_nonce');
		?>
		<dev id="knawatproduct-metabox-content">
			<strong><?php _e('Update Knawat Product', 'dropshipping-woocommerce'); ?></strong>
			<p><?php _e('Warning: any changes made to the product will be overwritten', 'dropshipping-woocommerce'); ?></p>

			<a class="button button-secondary button-large" href="<?php echo $url; ?>">
				<?php _e('Get From Knawat', 'dropshipping-woocommerce'); ?>
			</a>
		<?php
	}

	/**
	 * Sync product from knawat
	 */
	public function knawat_dropshipwc_update_product(){
		if ( ! empty($_GET) && wp_verify_nonce($_GET['update_knawat_product_nonce'], 'update_knawat_product_action' ) ) {
			$product_id = (int) $_GET['product_id'];
			if ( !empty( $product_id ) ) {
				$product = new WC_Product( $product_id );
				$sku     = $product->get_sku();
				if( $sku != '' ){
					global $knawat_dropshipwc;
					$knawat_dropshipwc->common->knawat_dropshipwc_import_product_by_sku( $sku, true );
					$edit_product = add_query_arg( array(
						'knawat_product_sync' => '1',
						'post' => $product_id,
						'action' => 'edit'
					), admin_url( 'post.php' ));
					wp_safe_redirect($edit_product);
					exit();
				}
			}
			die( __('Invalid request.', 'dropshipping-woocommerce' ) );
		} else {
			die( __('You have not access to doing this operations.', 'dropshipping-woocommerce' ) );
		}
	}

	public function knawat_dropshipwc_bulk_actions( $bulk_actions ){
		$bulk_actions['knawat_update'] = __('Knawat Update', 'dropshipping-woocommerce' );
		return $bulk_actions;
	}

	public function knawat_dropshipwc_update_bulk_products($redirect, $doaction, $object_ids){
		//remove query args from URL first
		$redirect = remove_query_arg(array('knawat_update_done'), $redirect);
		//Update Products from Knawat MP
		if ($doaction == 'knawat_update'){
			foreach( $object_ids as $post_id){
				global $knawat_dropshipwc;
				$knawat_dropshipwc->common->knawat_dropshipwc_async_product_update_by_id($post_id, true);
			}
			$redirect = add_query_arg('knawat_update_done', count($object_ids), $redirect);
		}
		return $redirect;
	}
}
