<?php

/**
 * Knawat MP Importer class.
 *
 * @link       http://knawat.com/
 * @since      2.0.0
 * @category   Class
 * @author       Dharmesh Patel
 *
 * @package    Knawat_Dropshipping_Woocommerce
 * @subpackage Knawat_Dropshipping_Woocommerce/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include dependencies.
 */
if ( ! class_exists( 'WC_Product_Importer', false ) ) {
	include_once dirname( WC_PLUGIN_FILE ) . '/includes/import/abstract-wc-product-importer.php';
}

if ( class_exists( 'WC_Product_Importer', false ) ) :

	/**
	 * Knawat_Dropshipping_Woocommerce_Importer Class.
	 */
	class Knawat_Dropshipping_Woocommerce_Importer extends WC_Product_Importer {

		/**
		 * MP API Wrapper object
		 *
		 * @var integer
		 */
		protected $mp_api;

		/**
		 * Import Type
		 *
		 * @var string
		 */
		protected $import_type = 'full';

		/**
		 * Response Data
		 *
		 * @var object
		 */
		protected $data;

		/**
		 * Parameters which contains information regarding import.
		 *
		 * @var array
		 */
		public $params;

		/**
		 * __construct function.
		 *
		 * @access public
		 * @return void
		 */
		public function __construct( $import_type = 'full', $params = array() ) {

			$default_args = array(
				'import_id'         => 0,  // Import_ID
				'limit'             => 25, // Limit for Fetch Products
				'page'              => 1,  // Page Number
				'product_index'     => - 1, // product index needed incase of memory issuee or timeout
				'force_update'      => false, // Whether to force update existing items.
				'prevent_timeouts'  => true,  // Check memory and time usage and abort if reaching limit.
				'force_full_import' => 0,      // Option for import all products not updated only.
				'is_complete'       => false, // Is Import Complete?
				'products_total'    => - 1,
				'imported'          => 0,
				'failed'            => 0,
				'updated'           => 0,
			);

			$this->import_type	= $import_type;
			$this->params		= wp_parse_args( $params, $default_args );
			$this->mp_api		= new Knawat_Dropshipping_Woocommerce_API();
		}

		public function import() {

			$api_url          = '';
			$this->start_time = time();
			$data             = array(
				'imported' => array(),
				'failed'   => array(),
				'updated'  => array(),
			);
      
			$page = $this->params['page'];
			switch ( $this->import_type ) {
				case 'full':
					$knawat_last_imported = get_option( 'knawat_last_imported', false );
					$sorting = array(
						'sort' => array('field'=>'updated','order'=>'asc')
					);
					$sortData = '&'.http_build_query($sorting,'','&');
					$api_url 	= 'catalog/products/?limit=' . $this->params['limit'] . '&page='.$page.$sortData;
					if (!empty( $knawat_last_imported)) {
						$api_url .= '&lastupdate='.$knawat_last_imported;
					}
					$this->data = $this->mp_api->get( $api_url );
					break;

				case 'single':
					$sku = sanitize_text_field( $this->params['sku'] );
					if ( empty( $sku ) ) {
						return array(
							'status'  => 'fail',
							'message' => __( 'Please provide product sku.', 'dropshipping-woocommerce' ),
						);
					}
					$this->data = $this->mp_api->get( 'catalog/products/' . $sku );
					break;

				default:
					break;
			}

			if ( is_wp_error( $this->data ) ) {
				knawat_dropshipwc_logger( '[GET_PRODUCTS_FROM_API_ERROR] ' . $api_url . ' ' . $this->data->get_error_message() );

				return array(
					'status'  => 'fail',
					'message' => $this->data->get_error_message(),
				);
			} elseif ( ! isset( $this->data->products ) && ! ( 'single' === $this->import_type && isset( $this->data->product ) ) ) {
				knawat_dropshipwc_logger( '[GET_PRODUCTS_FROM_API_ERROR] ' . $api_url . ' ' . print_r( $this->data, true ) );

				return array(
					'status'  => 'fail',
					'message' => __( 'Something went wrong during get data from Knawat MP API. Please try again later.', 'dropshipping-woocommerce' ),
				);
			}

			$response = $this->data;
			$products = array();
			if ( 'single' === $this->import_type ) {
				if ( isset( $response->product->status ) && 'failed' == $response->product->status ) {
					$error_message = isset( $response->product->message ) ? $response->product->message : __( 'Something went wrong during get data from Knawat MP API. Please try again later.', 'dropshipping-woocommerce' );

					return array(
						'status'  => 'fail',
						'message' => $error_message,
					);
				}
				$products[] = $response->product;
			} else {
				$products = $response->products;

			}

			// Handle errors
			if ( isset( $products->code ) || ! is_array( $products ) ) {
				return array(
					'status'  => 'fail',
					'message' => __( 'Something went wrong during get data from Knawat MP API. Please try again later.', 'dropshipping-woocommerce' ),
				);
			}

			// Update Product totals.
			$this->params['products_total'] = count( $products );
			if ( empty( $products ) ) {
				$this->params['is_complete'] = true;

				return $data;
			}

			$lastUpdateDate = '';
			foreach ( $products as $index => $product ) {

				if ( $index <= $this->params['product_index'] ) {
					continue;
				}

				if(!empty($product->updated)){
					$lastUpdateDate = $product->updated;
				}


				$formated_data = $this->get_formatted_product( $product );
				$variations    = $formated_data['variations'];
				unset( $formated_data['variations'] );

				// Prevent new import for 0 qty products.
				$total_qty = 0;
				if ( ! empty( $variations ) ) {
					foreach ( $variations as $vars ) {
						$total_qty += isset( $vars['stock_quantity'] ) ? $vars['stock_quantity'] : 0;
					}
				}

				$knawat_options      = knawat_dropshipwc_get_options();
				$remove_outofstock 	 = isset( $knawat_options['remove_outofstock'] ) ? esc_attr( $knawat_options['remove_outofstock'] ) : 'no';
			
				if($total_qty == 0 && $remove_outofstock == 'yes'){
					$this->remove_zero_variation_product($formated_data['id'],$product->sku);
				}

				if ( isset( $formated_data['id'] ) && ! $this->params['force_update'] ) {
					// Fake it
					$result = array(
						'id'      => $formated_data['id'],
						'updated' => true,
					);
					if ( isset( $formated_data['raw_attributes'] ) && ! empty( $formated_data['raw_attributes'] ) ) {
						foreach ( $formated_data['raw_attributes'] as $attkey => $attvalue ) {
							if ( ! empty( $attvalue['taxonomy'] ) ) {
								$options = $this->get_existing_attribute_values( $formated_data['id'], $attvalue['name'] );
								if ( ! empty( $attvalue['value'] ) ) {
									foreach ( $attvalue['value'] as $opt ) {
										if ( ! in_array( $opt, $options ) ) {
											$options[] = $opt;
										}
									}
								}
								$formated_data['raw_attributes'][ $attkey ]['value'] = $options;
							}
						}
						$result = $this->process_item( $formated_data );
					}
				} else {
					if ( $total_qty > 0 ) {
						add_filter( 'woocommerce_new_product_data', array( $this, 'set_dokan_seller' ) );
						$result = $this->process_item( $formated_data );
						remove_filter( 'woocommerce_new_product_data', array( $this, 'set_dokan_seller' ) );
					} else {
						$this->params['product_index'] = $index;

						// Delete product from my products if not exists into WooCommerce and qty zero in Knawat
						global $knawat_dropshipwc;
						$knawat_dropshipwc->common->knawat_delete_mp_product_by_sku( $formated_data['sku'] );
						continue;
					}
				}
				if ( is_wp_error( $result ) ) {
					$result->add_data( array( 'data' => $formated_data ) );
					$data['failed'][] = $result;
				} else {
					if ( $result['updated'] ) {
						$data['updated'][] = $result['id'];
					} else {
						$data['imported'][] = $result['id'];
					}
					$product_id = $result['id'];

					/**
					 *  Pass Products Data to Knawat WooCommerce DropShipping WPML Support Plugin
					 */
					if(!empty($product_id)):
						do_action('wpml_import_translation_product',$product_id,$product);
					endif;

					if ( ! empty( $variations ) ) {

						foreach ( $variations as $vindex => $variation ) {
							$variation['parent_id'] = $product_id;
							add_filter( 'woocommerce_new_product_variation_data', array( $this, 'set_dokan_seller' ) );
							$variation_result = $this->process_item( $variation );
							remove_filter( 'woocommerce_new_product_variation_data', array( $this, 'set_dokan_seller' ) );
							if ( is_wp_error( $variation_result ) ) {
								$variation_result->add_data( array( 'data' => $formated_data ) );
								$variation_result->add_data( array( 'variation' => 1 ) );
								$data['failed'][] = $variation_result;
							}
						}
					}
				}
				$this->params['product_index'] = $index;

				if ( $this->params['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
					break;
				}
			}

			/*
			if( $this->params['products_total'] === 0 ){
				$this->params['is_complete'] = true;
			}elseif( ( $this->params['products_total'] < $this->params['limit'] ) && ( $this->params['products_total'] == ( $this->params['product_index'] + 1 ) ) ){
				$this->params['is_complete'] = true;
			}else{
				$this->params['is_complete'] = false;
			}*/

			$this->params['is_complete'] = $this->params['products_total'] === 0;

			$datetime = new DateTime($lastUpdateDate);
			$lastUpdateTime = (int) ($datetime->getTimestamp().$datetime->format('u')/ 1000);
			// if($this->params['products_total'] < $this->params['limit'] && $knawat_last_imported == $lastUpdateTime){
			// 	$this->params['is_complete'] = true;
			// }

			if(!empty($lastUpdateDate) && $knawat_last_imported != $lastUpdateTime){
				//update product import date 			
				$datetime = new DateTime($lastUpdateDate);
				$lastUpdateTime = (int) ($datetime->getTimestamp().$datetime->format('u')/ 1000);
				update_option( 'knawat_last_imported', $lastUpdateTime , false );
				$this->params['page'] = 1;
				$this->params['product_index'] = -1;
			} else if( $this->params['products_total'] == ( $this->params['product_index'] + 1 ) ){
				$this->params['page'] += 1;
			}
			return $data;
		}

		public function get_formatted_product( $product ) {
			if ( empty( $product ) ) {
				return $product;
			}
			$active_langs = array();
			$attributes   = array();
			$default_lang = get_locale();
			$default_lang = explode( '_', $default_lang );
			$default_lang = $default_lang[0];

			$active_plugins = knawat_dropshipwc_get_activated_plugins();

			if ( $active_plugins['qtranslate-xt'] || $active_plugins['qtranslate-x'] ) {
				global $q_config;
				$default_lang = isset( $q_config['default_language'] ) ? sanitize_text_field( $q_config['default_language'] ) : $default_lang;
				$active_langs = isset( $q_config['enabled_languages'] ) ? $q_config['enabled_languages'] : array();
			}

			$new_product = array();
			$product_id  = wc_get_product_id_by_sku( $product->sku );
			if ( $product_id ) {
				$new_product['id'] = $product_id;
			} else {
				$new_product['sku'] = $product->sku;
			}

			if ( ! $product_id || $this->params['force_update'] ) {

				if ( isset( $product->variations ) && ! empty( $product->variations ) ) {
					$new_product['type'] = 'variable';
				}
				$new_product['name']        = isset( $product->name->$default_lang ) ? sanitize_text_field( $product->name->$default_lang ) : '';
				$new_product['description'] = isset( $product->description->$default_lang ) ? sanitize_textarea_field( $product->description->$default_lang ) : '';

				if ( ( $active_plugins['qtranslate-x'] || $active_plugins['qtranslate-xt'] ) && ! empty( $active_langs ) ) {

					$new_product['name']        = '';
					$new_product['description'] = '';
					$categories                 = array();

					foreach ( $active_langs as $active_lang ) {
						if ( isset( $product->name->$active_lang ) ) {
							$new_product['name'] .= '[:' . $active_lang . ']' . $product->name->$active_lang;
						}
						if ( isset( $product->description->$active_lang ) ) {
							$new_product['description'] .= '[:' . $active_lang . ']' . $product->description->$active_lang;
						}
					}
					if ( $new_product['name'] != '' ) {
						$new_product['name'] .= '[:]';
					}
					if ( $new_product['description'] != '' ) {
						$new_product['description'] .= '[:]';
					}
				}

				// $new_product['short_description'] = $new_product['description'];
				$new_product['short_description'] = '';

				// Added Meta Data.
				$new_product['meta_data']   = array();
				$new_product['meta_data'][] = array(
					'key'   => 'dropshipping',
					'value' => 'knawat',
				);

				// Formatting Image Data
				if ( $active_plugins['featured-image-by-url'] && isset( $product->images ) && ! empty( $product->images ) ) {
					$images                     = $product->images;
					$new_product['meta_data'][] = array(
						'key'   => '_knawatfibu_url',
						'value' => array_shift( $images ),
					);
					if ( ! empty( $images ) ) {
						$new_product['meta_data'][] = array(
							'key'   => '_knawatfibu_wcgallary',
							'value' => implode( ',', $images ),
						);
					}
				} elseif ( isset( $product->images ) && ! empty( $product->images ) ) {
					$images                      = $product->images;
					$new_product['raw_image_id'] = array_shift( $images );
					if ( ! empty( $images ) ) {
						$new_product['raw_gallery_image_ids'] = $images;
					}
				}

				if ( isset( $product->attributes ) && ! empty( $product->attributes ) ) {
					foreach ( $product->attributes as $attribute ) {
						$attribute_name    = isset( $attribute->name ) ? $this->attribute_languagfy( $attribute->name ) : '';
						$attribute_options = array();
						if ( isset( $attribute->options ) && ! empty( $attribute->options ) ) {
							foreach ( $attribute->options as $attributevalue ) {
								$attribute_formated = $this->attribute_languagfy( $attributevalue );
								if ( ! in_array( $attribute_formated, $attribute_options ) && ! empty( $attribute_formated ) ) {
									$attribute_options[] = $attribute_formated;
								}
							}
						}
						// continue if no attribute name found.
						if ( $attribute_name == '' || empty( $attribute_options ) ) {
							continue;
						}

						if ( isset( $attributes[ $attribute_name ] ) ) {
							if ( ! empty( $attribute_options ) ) {
								$attributes[ $attribute_name ] = array_unique( array_merge( $attributes[ $attribute_name ], $attribute_options ) );
							}
						} else {
							$attributes[ $attribute_name ] = $attribute_options;
						}
					}
				}
			}
			$knawat_options      = knawat_dropshipwc_get_options();
			$categorize_products = isset( $knawat_options['categorize_products'] ) ? esc_attr( $knawat_options['categorize_products'] ) : 'no';
			$remove_outofstock 	 = isset( $knawat_options['remove_outofstock'] ) ? esc_attr( $knawat_options['remove_outofstock'] ) : 'no';
			$tag                 = '';

			if ( ( $active_plugins['qtranslate-xt'] || $active_plugins['qtranslate-x'] ) && ! empty( $active_langs ) ) {

				foreach ( $product->categories as $key => $category ) {
					foreach ( $active_langs as $active_lang ) {
						if ( isset( $category->name->$active_lang ) ) {
							$tag .= '[:' . $active_lang . ']' . $category->name->$active_lang;
						}
					}
					if ( $tag != '' ) {
						$tag .= '[:]';
					}
					global $wpdb;

					$parentId = isset( $category->parentId ) ? $category->parentId : '';

					if ( $categorize_products == 'yes_as_tags' ) {
						$tag_ids[] = $this->set_product_taxonomy( $tag, 'product_tag', $category->id, $parentId );
					}

					if ( $categorize_products == 'yes' ) {
						$category_ids[] = $this->set_product_taxonomy( $tag, 'product_cat', $category->id, $parentId );
					}

					$tag = '';
				}

				$new_product['category_ids'] = $category_ids;
				$new_product['tag_ids']      = $tag_ids;

			} else {
				foreach ( $product->categories as $category ) {
					$tag .= isset( $category->name->$default_lang ) ? sanitize_text_field( $category->name->$default_lang ) : '';
					$tag .= iconv(mb_detect_encoding($tag),'UTF-8',$tag);

					$parentId = isset( $category->parentId ) ? $category->parentId : '';

					if ( $categorize_products == 'yes_as_tags' ) {
						$tag_ids[] = $this->set_product_taxonomy( $tag, 'product_tag', $category->id, $parentId );
					}

					if ( $categorize_products == 'yes' ) {
						$category_ids[] = $this->set_product_taxonomy( $tag, 'product_cat', $category->id, $parentId );
					}

					$tag = '';
				}

				$new_product['category_ids'] = $category_ids;
				$new_product['tag_ids']      = $tag_ids;
			}

			$variations     = array();
			$var_attributes = array();
		
			if ( isset( $product->variations ) && ! empty( $product->variations ) ) {
				foreach ( $product->variations as $variation ) {

					if($variation->sale_price != 0){
						$temp_variant = array();
						$varient_id   = wc_get_product_id_by_sku( $variation->sku );
						if ( $varient_id && $varient_id > 0 ) {
							$temp_variant['id'] = $varient_id;
							// Update name as its name is added as null in the first time - related to migration task
							if ( empty( $temp_variant['name'] ) && empty( $new_product['name'] ) ) {
								if ( ( $active_plugins['qtranslate-xt'] || $active_plugins['qtranslate-x'] ) && ! empty( $active_langs ) ) {

									$new_product['name'] = '';

									foreach ( $active_langs as $active_lang ) {
										if ( isset( $product->name->$active_lang ) ) {
											$new_product['name'] .= '[:' . $active_lang . ']' . $product->name->$active_lang;
										}
									}
									if ( $new_product['name'] != '' ) {
										$new_product['name'] .= '[:]';
									}
								}
								$temp_variant['name'] = $new_product['name'];
							}
						} else {
							$temp_variant['sku']  = $variation->sku;
							$temp_variant['name'] = $new_product['name'];
							// handeled test case where variation =1 and parent sku != child sku
							if ( count( $product->variations ) == 1 && $temp_variant['sku'] == $product->sku ) {
								$temp_variant['type'] = 'simple';
							} else {
								$temp_variant['type'] = 'variation';
							}
						}

						// Add Meta Data.
						$temp_variant['meta_data'] = array();
						if ( is_numeric( $variation->sale_price ) ) {
							$temp_variant['price'] = wc_format_decimal( $variation->sale_price );
						}
						if ( is_numeric( $variation->market_price ) ) {
							$temp_variant['regular_price'] = wc_format_decimal( $variation->market_price );
						}
						if ( is_numeric( $variation->sale_price ) ) {
							$temp_variant['sale_price'] = wc_format_decimal( $variation->sale_price );
						}
						$temp_variant['manage_stock']   = true;
						$temp_variant['stock_quantity'] = $this->parse_stock_quantity_field( $variation->quantity );
						$temp_variant['meta_data'][]    = array(
							'key'   => '_knawat_cost',
							'value' => wc_format_decimal( $variation->cost_price ),
						);

						if ( $varient_id && $varient_id > 0 && ! $this->params['force_update'] ) {
							// Update Data for existing Variend Here.
						} else {
							$temp_variant['weight'] = wc_format_decimal( $variation->weight );
							if ( isset( $variation->attributes ) && ! empty( $variation->attributes ) ) {
								foreach ( $variation->attributes as $attribute ) {
									$temp_attribute_name  = isset( $attribute->name ) ? $this->attribute_languagfy( $attribute->name ) : '';
									$temp_attribute_value = isset( $attribute->option ) ? $this->attribute_languagfy( $attribute->option ) : '';

									// continue if no attribute name found.
									if ( $temp_attribute_name == '' ) {
										continue;
									}

									$temp_var_attribute               = array();
									$temp_var_attribute['name']       = $temp_attribute_name;
									$temp_var_attribute['value']      = array( $temp_attribute_value );
									$temp_var_attribute['taxonomy']   = true;
									$temp_variant['raw_attributes'][] = $temp_var_attribute;

									// Add attribute name to $var_attributes for make it taxonomy.
									$var_attributes[] = $temp_attribute_name;

									if ( isset( $attributes[ $temp_attribute_name ] ) ) {
										if ( ! in_array( $temp_attribute_value, $attributes[ $temp_attribute_name ] ) ) {
											$attributes[ $temp_attribute_name ][] = $temp_attribute_value;
										}
									} else {
										$attributes[ $temp_attribute_name ][] = $temp_attribute_value;
									}
								}
							}
						}
					}
					$variations[] = $temp_variant;
				}
			}

			if ( ! empty( $attributes ) ) {
				foreach ( $attributes as $name => $value ) {
					$temp_raw            = array();
					$temp_raw['name']    = $name;
					$temp_raw['value']   = $value;
					$temp_raw['visible'] = true;
					if ( in_array( $name, $var_attributes ) ) {
						$temp_raw['taxonomy'] = true;
						$temp_raw['default']  = isset( $value[0] ) ? $value[0] : '';
					}
					$new_product['raw_attributes'][] = $temp_raw;
				}
			}

			if(!empty($variations)):
				$new_product['variations'] = $variations;
			endif;

		
			return $new_product;
		}


		/**
		 * Remove Zero Variation Product From the List
		*/
		public function remove_zero_variation_product($productID,$sku){
			try{
				
				if(isset($productID) && !empty($sku)){
					$api_url = 'catalog/products/'.$sku;
					$this->mp_api->delete($api_url);

					do_action('remove_stokout_product',$productID);
					$this->wc_deleteProduct($productID);

					if($this->params['force_update'] && $this->import_type == 'single'){
						wp_redirect(admin_url('edit.php?post_type=product'));
						exit;
					}
				
				}

			}catch (Exception $ex) {
				//skip it
			}
		}

		/**
		 * Set variation data.
		 *
		 * @param WC_Product $variation Product instance.
		 * @param array      $data Item data.
		 *
		 * @return WC_Product|WP_Error
		 * @throws Exception If data cannot be set.
		 */
		protected function set_variation_data( &$variation, $data ) {
			$parent = false;

			// Check if parent exist.
			if ( isset( $data['parent_id'] ) ) {
				$parent = wc_get_product( $data['parent_id'] );

				if ( $parent ) {
					$variation->set_parent_id( $parent->get_id() );
				}
			}

			// Stop if parent does not exists.
			if ( ! $parent ) {
				return new WP_Error( 'woocommerce_product_importer_missing_variation_parent_id', __( 'Variation cannot be imported: Missing parent ID or parent does not exist yet.', 'dropshipping-woocommerce' ), array( 'status' => 401 ) );
			}

			if ( isset( $data['raw_attributes'] ) ) {
				$attributes        = array();
				$parent_attributes = $this->get_variation_parent_attributes( $data['raw_attributes'], $parent );

				foreach ( $data['raw_attributes'] as $attribute ) {
					$attribute_id = 0;

					// Get ID if is a global attribute.
					if ( ! empty( $attribute['taxonomy'] ) ) {
						$attribute_id = $this->get_attribute_taxonomy_id( $attribute['name'] );
					}

					if ( $attribute_id ) {
						$attribute_name_raw = wc_attribute_taxonomy_name_by_id( $attribute_id );
						$attribute_name     = sanitize_title( $attribute_name_raw );
					} else {
						$attribute_name_raw = sanitize_title( $attribute['name'] );
						$attribute_name     = sanitize_title( $attribute['name'] );
					}

					if ( ! isset( $parent_attributes[ $attribute_name ] ) || ! $parent_attributes[ $attribute_name ]->get_variation() ) {
						continue;
					}

					$attribute_key   = sanitize_title( $parent_attributes[ $attribute_name ]->get_name() );
					$attribute_value = isset( $attribute['value'] ) ? current( $attribute['value'] ) : '';

					if ( $parent_attributes[ $attribute_name ]->is_taxonomy() ) {
						// If dealing with a taxonomy, we need to get the slug from the name posted to the API.
						$term = get_term_by( 'name', $attribute_value, $attribute_name_raw );

						if ( $term && ! is_wp_error( $term ) ) {
							$attribute_value = $term->slug;
						} else {
							$attribute_value = sanitize_title( $attribute_value );
						}
					}

					$attributes[ $attribute_key ] = $attribute_value;
				}

				$variation->set_attributes( $attributes );
			}
		}

		/**
		 * Parse a float value field.
		 *
		 * @param string $value Field value.
		 *
		 * @return float|string
		 */
		public function parse_float_field( $value ) {
			if ( '' === $value ) {
				return $value;
			}

			// Remove the ' prepended to fields that start with - if needed.
			$value = $this->unescape_negative_number( $value );

			return floatval( $value );
		}

		/**
		 * Parse the stock qty field.
		 *
		 * @param string $value Field value.
		 *
		 * @return float|string
		 */
		public function parse_stock_quantity_field( $value ) {
			if ( '' === $value ) {
				return $value;
			}

			// Remove the ' prepended to fields that start with - if needed.
			$value = $this->unescape_negative_number( $value );

			return wc_stock_amount( $value );
		}

		/**
		 * Get Import Parameters
		 *
		 * @param string $value Field value.
		 *
		 * @return float|string
		 */
		public function get_import_params() {
			return $this->params;
		}

		/**
		 * The exporter prepends a ' to fields that start with a - which causes
		 * issues with negative numbers. This removes the ' if the input is still a valid
		 * number after removal.
		 *
		 * @param string $value A numeric string that may or may not have ' prepended.
		 *
		 * @return string
		 * @since 2.0.0
		 */
		function unescape_negative_number( $value ) {
			if ( 0 === strpos( $value, "'-" ) ) {
				$unescaped = trim( $value, "'" );
				if ( is_numeric( $unescaped ) ) {
					return $unescaped;
				}
			}

			return $value;
		}

		/**
		 * Setup product seller for add dokan support.
		 *
		 * @param array $product_data Product data for create new product
		 *
		 * @return array $product_data Altered Product data
		 * @since 2.0.0
		 */
		function set_dokan_seller( $product_data ) {
			if ( knawat_dropshipwc_is_dokan_active() ) {
				$dokan_seller = knawat_dropshipwc_get_options( 'dokan_seller' );
				if ( isset( $product_data['post_author'] ) && $dokan_seller > 0 ) {
					$product_data['post_author'] = $dokan_seller;
				}
			}

			return $product_data;
		}

		/**
		 * Get Existing Attribute options by product ID and Attribute name.
		 *
		 * @param array $product_id Product ID for get attribute options
		 * @param array $attribute_name Attribute name for get attribute options
		 *
		 * @return array $terms Attribute options
		 * @since 2.0.2
		 */
		function get_existing_attribute_values( $product_id, $attribute_name ) {
			if ( empty( $product_id ) || empty( $attribute_name ) ) {
				return array();
			}
			$terms = array();

			$attribute_id = $this->get_attribute_taxonomy_id( $attribute_name );
			// Get name.
			$attribute_name = $attribute_id ? wc_attribute_taxonomy_name_by_id( $attribute_id ) : $attribute_name;

			$product             = wc_get_product( $product_id );
			$existing_attributes = $product->get_attributes();
			if ( ! empty( $existing_attributes ) && ! empty( $product ) ) {
				foreach ( $existing_attributes as $existing_attribute ) {
					if ( $existing_attribute->get_name() === $attribute_name ) {
						if ( taxonomy_exists( $attribute_name ) ) {
							foreach ( $existing_attribute->get_options() as $option ) {
								if ( is_int( $option ) ) {
									$term = get_term_by( 'id', $option, $attribute_name );
								} else {
									$term = get_term_by( 'name', $option, $attribute_name );
									if ( ! $term || is_wp_error( $term ) ) {
										$new_term = wp_insert_term( $option, $attribute_name );
										$term     = is_wp_error( $new_term ) ? false : get_term_by( 'id', $new_term['term_id'], $attribute_name );
									}
								}
								if ( $term && ! is_wp_error( $term ) && isset( $term->name ) ) {
									$terms[] = $term->name;
								}
							}
						}
					}
				}
			}

			return $terms;
		}

		/**
		 * Get Formated string with qtranslate-X languege wrappers for lang object.
		 *
		 * @param array $lang_object Object of values with lang keys
		 *
		 * @return string $formated_value Formated string with language wrappers.
		 * @since 2.0.2
		 */
		function attribute_languagfy( $lang_object ) {
			if ( empty( $lang_object ) ) {
				return $lang_object;
			}
			$active_langs   = array();
			$default_lang   = get_locale();
			$default_lang   = explode( '_', $default_lang );
			$default_lang   = $default_lang[0];
			$active_plugins = knawat_dropshipwc_get_activated_plugins();
			if ( $active_plugins['qtranslate-x'] || $active_plugins['qtranslate-xt'] ) {
				global $q_config;
				$default_lang = isset( $q_config['default_language'] ) ? sanitize_text_field( $q_config['default_language'] ) : $default_lang;
				$active_langs = isset( $q_config['enabled_languages'] ) ? $q_config['enabled_languages'] : array();
			}

			$formated_value = isset( $lang_object->$default_lang ) ? sanitize_text_field( $lang_object->$default_lang ) : '';
			// if attribute name is blank then take a chance for EN.
			if ( $formated_value == '' ) {
				$formated_value = isset( $lang_object->en ) ? $lang_object->en : '';
			}
			// if attribute name is blank then take a chance for TR.
			if ( $formated_value == '' ) {
				$formated_value = isset( $lang_object->tr ) ? $lang_object->tr : '';
			}

			if ( ( $active_plugins['qtranslate-x'] || $active_plugins['qtranslate-xt'] ) && ! empty( $active_langs ) ) {
				$formated_value = '';
				foreach ( $active_langs as $active_lang ) {
					if ( isset( $lang_object->$active_lang ) ) {
						$formated_value .= '[:' . $active_lang . ']' . $lang_object->$active_lang;
					}
				}
				if ( $formated_value != '' ) {
					$formated_value .= '[:]';
				}
			}

			return $formated_value;
		}


		/**
		 * Set product category and tags.
		 *
		 * @param string $taxonomy_type product taxonomy type
		 * @param string $tag product taxonomy name
		 * @param int    $tax_id use for as tax id
		 * @param int    $parent_id use for as parent id
		 *
		 * @return string
		 * @since 2.0.7
		 */

		public function set_product_taxonomy( $tag, $taxonomy_type, $tax_id, $parent_id ) {

			$product_taxonomy = term_exists( sanitize_title( $tag ), $taxonomy_type );

			if ( $product_taxonomy == 0 && $product_taxonomy == null ) {

				$new_id = wp_insert_term( $tag, $taxonomy_type );

				update_term_meta( $new_id['term_id'], 'tax_api_id', $tax_id );

				if ( ! empty( $parent_id ) ) {
					global $wpdb;
					$parentsID = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}termmeta WHERE `meta_key` LIKE 'tax_api_id' AND `meta_value` = " . $parent_id );

					if ( ! empty( $parentsID->term_id ) ) {
						$update = wp_update_term(
							$new_id['term_id'],
							$taxonomy_type,
							array(
								'parent' => $parentsID->term_id,
							)
						);
					}
				}
			}

			$taxonomy_ids = ! empty( $product_taxonomy['term_id'] ) ? $product_taxonomy['term_id'] : $new_id['term_id'];

			return $taxonomy_ids;
		}

		
	/**
	* Method to delete Woo Product
	*
	* @param int $id the product ID.
	* @param bool $force true to permanently delete product, false to move to trash.
	* @return WP_Error|boolean
	*/
	public function wc_deleteProduct($product_ID){
			try{

				$product = wc_get_product($product_ID);
				if(empty($product)){
					return false;
				}
				
				if ($product->is_type('variable')){
					foreach ($product->get_children() as $child_id)
					{
						$child = wc_get_product($child_id);
						$child->delete();
					}
				}

				$product->delete(true);
				$result = $product->get_id() > 0 ? false : true;
				
				if ($parent_id = wp_get_post_parent_id($product_ID)){
					wc_delete_product_transients($parent_id);
				}
				return true;

			}catch (Exception $ex) {
					//skip it
		}
	}

}

endif;
