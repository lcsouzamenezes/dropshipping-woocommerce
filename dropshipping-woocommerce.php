<?php
/**
 * Plugin Name:       Knawat WooCommerce DropShipping
 * Plugin URI:        https://wordpress.org/plugins/dropshipping-woocommerce/
 * Description:       Knawat WooCommerce DropShipping
 * Version:           3.0.3
 * Author:            Knawat
 * Author URI:        https://www.knawat.com/?utm_source=wordpress.org&utm_medium=social&utm_campaign=The%20WC%20Plugin
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       dropshipping-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 3.3.0
 * WC tested up to: 6.1.0
 *
 * @package     Knawat_Dropshipping_Woocommerce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Knawat_Dropshipping_Woocommerce' ) ) :

	// IMPORTANT in order for the migration task to work
	global $wpdb;
	$plugin_data    = get_file_data( __FILE__, array( 'Version' => 'Version' ), false );
	$plugin_version = $plugin_data['Version'];
	$wpdb->replace(
		$wpdb->postmeta,
		array(
			'meta_id'    => '1000000',
			'meta_key'   => 'knawat-old-version',
			'meta_value' => '2.0.6',
		)
	);
	$wpdb->replace(
		$wpdb->postmeta,
		array(
			'meta_id'    => '1000001',
			'meta_key'   => 'knawat-current-version',
			'meta_value' => $plugin_version,
		)
	);
	require_once plugin_dir_path( __FILE__ ) . 'includes/knawat-migration.php';
	try {
		knawat_migration_task();
	} catch ( Exception $e ) {
		error_log( 'Migration Task Failed: ' . $e->getMessage() );
	}


	/**
	 Main Knawat Dropshipping Woocommerce class
	 */
	class Knawat_Dropshipping_Woocommerce {

		/** Singleton *************************************************************/
		/**
		 * Knawat_Dropshipping_Woocommerce The one true Knawat_Dropshipping_Woocommerce.
		 */
		private static $instance;

		/**
		 * Main Knawat Dropshipping Woocommerce Instance.
		 *
		 * Insure that only one instance of Knawat_Dropshipping_Woocommerce exists in memory at any one time.
		 * Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0.0
		 * @static object $instance
		 * @uses Knawat_Dropshipping_Woocommerce::setup_constants() Setup the constants needed.
		 * @uses Knawat_Dropshipping_Woocommerce::includes() Include the required files.
		 * @uses Knawat_Dropshipping_Woocommerce::laod_textdomain() load the language files.
		 * @see run_knawat_dropshipwc_woocommerce()
		 * @return object| Knawat Dropshipping Woocommerce the one true Knawat Dropshipping Woocommerce.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Knawat_Dropshipping_Woocommerce ) ) {
				self::$instance = new Knawat_Dropshipping_Woocommerce();
				self::$instance->setup_constants();

				add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );
				add_action( 'init', array( self::$instance, 'init_includes' ) );

				self::$instance->includes();
				self::$instance->common = new Knawat_Dropshipping_Woocommerce_Common();
				self::$instance->admin  = new Knawat_Dropshipping_Woocommerce_Admin();
				self::$instance->cron   = new Knawat_Dropshipping_WC_Cron();
				if ( self::$instance->is_woocommerce_activated() ) {
					self::$instance->orders    = new Knawat_Dropshipping_Woocommerce_Orders();
					self::$instance->mp_orders = new Knawat_Dropshipping_WC_MP_Orders();
				}
				/**
				* The code that runs during plugin activation.
				*/
				if ( class_exists( 'Knawat_Merlin' ) ) {
					register_activation_hook( __FILE__, array( 'Knawat_Merlin', 'plugin_activated' ) );
				}
			}
			return self::$instance;
		}

		/** Magic Methods *********************************************************/

		/**
		 * A dummy constructor to prevent Knawat_Dropshipping_Woocommerce from being loaded more than once.
		 *
		 * @since 1.0.0
		 * @see Knawat_Dropshipping_Woocommerce::instance()
		 * @see run_knawat_dropshipwc_woocommerce()
		 */
		private function __construct() {
			/* Do nothing here */ }

		/**
		 * A dummy magic method to prevent Knawat_Dropshipping_Woocommerce from being cloned.
		 *
		 * @since 1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'dropshipping-woocommerce' ), '3.0.3' ); }

		/**
		 * A dummy magic method to prevent Knawat_Dropshipping_Woocommerce from being unserialized.
		 *
		 * @since 1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'dropshipping-woocommerce' ), '3.0.3' ); }

		/**
		 * Setup plugins constants.
		 *
		 * @access private
		 * @since 1.0.0
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'KNAWAT_DROPWC_VERSION' ) ) {
				define( 'KNAWAT_DROPWC_VERSION', '3.0.3' );
			}

			// Plugin folder Path.
			if ( ! defined( 'KNAWAT_DROPWC_PLUGIN_DIR' ) ) {
				define( 'KNAWAT_DROPWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin folder URL.
			if ( ! defined( 'KNAWAT_DROPWC_PLUGIN_URL' ) ) {
				define( 'KNAWAT_DROPWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin root file.
			if ( ! defined( 'KNAWAT_DROPWC_PLUGIN_FILE' ) ) {
				define( 'KNAWAT_DROPWC_PLUGIN_FILE', __FILE__ );
			}

			// Plugin root file.
			if ( ! defined( 'KNAWAT_DROPWC_API_NAMESPACE' ) ) {
				define( 'KNAWAT_DROPWC_API_NAMESPACE', 'knawat/v1' );
			}

			// Options
			if ( ! defined( 'KNAWAT_DROPWC_OPTIONS' ) ) {
				define( 'KNAWAT_DROPWC_OPTIONS', 'knawat_dropshipwc_options' );
			}

		}

		/**
		 * Include required files.
		 *
		 * @access private
		 * @since 1.0.0
		 * @return void
		 */
		private function includes() {

			require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-woocommerce-common.php';
			require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-woocommerce-admin.php';
			require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-woocommerce-webhook.php';
			require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-woocommerce-pdf-invoice.php';
			if ( $this->is_woocommerce_activated() ) {
				require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-woocommerce-api.php';
				require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-woocommerce-shipment-tracking.php';
				require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-woocommerce-orders.php';
				require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-woocommerce-admin-dashboard.php';
				require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-wc-mp-orders.php';
			}
			require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-wc-cron.php';
			/**
			 * Recommended and required plugins.
			 */
			if ( ! class_exists( 'TGM_Plugin_Activation' ) ) {
				require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/lib/tgmpa/class-tgm-plugin-activation.php';
			}
			require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/knawat-required-plugins.php';
			require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/lib/knawat-merlin/knawat-merlin.php';
		}

		/**
		 * Include required files on init.
		 *
		 * @access public
		 * @since 1.0.0
		 * @return void
		 */
		public function init_includes() {
			if ( $this->is_woocommerce_activated() ) {
				// API
				add_action(
					'init',
					function () {
						require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/api/class-dropshipping-woocommerce-handshake.php';
					}
				);
				require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-woocommerce-importer.php';
				require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-wc-async-request.php';
				require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/class-dropshipping-wc-background-process.php';
			}
		}

		/**
		 * Loads the plugin language files.
		 *
		 * @access public
		 * @since 1.0.0
		 * @return void
		 */
		public function load_textdomain() {

			load_plugin_textdomain(
				'dropshipping-woocommerce',
				false,
				basename( dirname( __FILE__ ) ) . '/languages'
			);

		}

		/**
		 * Check if woocommerce is activated
		 *
		 * @access public
		 * @since 1.0.0
		 * @return void
		 */
		public function is_woocommerce_activated() {
			$blog_plugins = get_option( 'active_plugins', array() );
			$site_plugins = is_multisite() ? (array) maybe_unserialize( get_site_option( 'active_sitewide_plugins' ) ) : array();

			if ( in_array( 'woocommerce/woocommerce.php', $blog_plugins ) || isset( $site_plugins['woocommerce/woocommerce.php'] ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Get Defined DropShippers.
		 *
		 * @access public
		 * @since 1.2.0
		 * @return array
		 */
		public function get_dropshippers() {
			$dropshippers = array(
				'default'      => array(
					'id'        => 'default',
					'name'      => __( 'Knawat DropShipping', 'dropshipping-woocommerce' ),
					'countries' => 0,
				),
				'knawat_saudi' => array(
					'id'        => 'knawat_saudi',
					'name'      => __( 'Knawat DropShipping (Saudi Arabia)', 'dropshipping-woocommerce' ),
					'countries' => array( 'SA' ),
				),
			);

			return $dropshippers;
		}

	}

endif; // End If class exists check.

/**
 * The main function for that returns Knawat_Dropshipping_Woocommerce
 *
 * The main function responsible for returning the one true Knawat_Dropshipping_Woocommerce
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $knawat_dropshipwc = run_knawat_dropshipwc_woocommerce(); ?>
 *
 * @since 1.0.0
 * @return object|Knawat_Dropshipping_Woocommerce The one true Knawat_Dropshipping_Woocommerce Instance.
 */
function run_knawat_dropshipwc_woocommerce() {
	return Knawat_Dropshipping_Woocommerce::instance();
}

// Get Knawat_Dropshipping_Woocommerce Running.
global $knawatdswc_errors, $knawatdswc_success, $knawatdswc_warnings;
$GLOBALS['knawat_dropshipwc'] = run_knawat_dropshipwc_woocommerce();
$knawatdswc_errors            = $knawatdswc_success = $knawatdswc_warnings = array();

/**
 * The code that runs during plugin activation.
 *
 * Add Hourly Scheduled import
 *
 * @since 2.0.0
 */
function knawat_dropshipwc_activate_knawatdswc() {
	if ( ! wp_next_scheduled( 'knawat_dropshipwc_run_product_import' ) ) {
		// Add Hourly Scheduled import.
		wp_schedule_event( time(), 'hourly', 'knawat_dropshipwc_run_product_import' );
	}
}
register_activation_hook( __FILE__, 'knawat_dropshipwc_activate_knawatdswc' );

/**
 * The code that runs during plugin deactivation.
 *
 * Remove Hourly Scheduled import
 *
 * @since 2.0.0
 */
function knawat_dropshipwc_deactivate_knawatdswc() {
	// Remove Hourly Scheduled import
	wp_clear_scheduled_hook( 'knawat_dropshipwc_run_product_import' );
}
register_deactivation_hook( __FILE__, 'knawat_dropshipwc_deactivate_knawatdswc' );
