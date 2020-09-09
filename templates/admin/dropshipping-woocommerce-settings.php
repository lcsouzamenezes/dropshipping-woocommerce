<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

$knawat_options = knawat_dropshipwc_get_options();
$mp_consumer_key = isset( $knawat_options['mp_consumer_key'] ) ? esc_attr( $knawat_options['mp_consumer_key'] ) : '';
$mp_consumer_secret = isset( $knawat_options['mp_consumer_secret'] ) ? esc_attr( $knawat_options['mp_consumer_secret'] ) : '';
$token_status = isset( $knawat_options['token_status'] ) ? esc_attr( $knawat_options['token_status'] ) : 'invalid';
$product_batch = isset( $knawat_options['product_batch'] ) ? esc_attr( $knawat_options['product_batch'] ) : 25;
$dokan_seller = isset( $knawat_options['dokan_seller'] ) ? esc_attr( $knawat_options['dokan_seller'] ) : -1;
?>
<div class="knawat_dropshipwc_settings">

	<h3><?php esc_attr_e( 'Settings', 'dropshipping-woocommerce' ); ?></h3>
	<p style="margin: 0px;"><strong><?php _e( 'Note: Only orders in Processing status will be sent to Knawat to processs.','dropshipping-woocommerce' ); ?></strong></p>
	<p style="margin: 0px;"><?php _e( 'You need a Knawat consumer key and consumer secret for import products into your store from knawat.com','dropshipping-woocommerce' ); ?></p>
	<form method="post" id="knawatds_setting_form">
		<table class="form-table">
			<tbody>
				<?php do_action( 'knawat_dropshipwc_before_settings_section' ); ?>

				<tr class="knawat_dropshipwc_row">
					<th scope="row">
						<?php _e( 'Knawat Consumer Key','dropshipping-woocommerce' ); ?>
					</th>
					<td>
						<input class="mp_consumer_key regular-text" name="knawat[mp_consumer_key]" type="text" value="<?php if ( $mp_consumer_key != '' ) { echo $mp_consumer_key; } ?>" />
						<p class="description" id="mp_consumer_key-description">
							<?php
							printf( '%s <a href="https://knawat.com/wp-content/uploads/2018/09/knawat_api_key_and_secret.png" target="_blank">%s</a>',
								__('You can get your Knawat Consumer Key from your <strong>Dashboard > Store settings</strong>', 'dropshipping-woocommerce' ),
								__('click to see the image', 'dropshipping-woocommerce' )
							);
							?>
						</p>
					</td>
				</tr>

				<tr class="knawat_dropshipwc_row">
					<th scope="row">
						<?php _e( 'Knawat Consumer Secret','dropshipping-woocommerce' ); ?>
					</th>
					<td>
						<input class="mp_consumer_secret regular-text" name="knawat[mp_consumer_secret]" type="text" value="<?php if ( $mp_consumer_secret != '' ) { echo $mp_consumer_secret; } ?>" />
						<p class="description" id="mp_consumer_secret-description">
							<?php
							printf( '%s <a href="https://knawat.com/wp-content/uploads/2018/09/knawat_api_key_and_secret.png" target="_blank">%s</a>',
								__('You can get your Knawat Consumer Secret from your <strong>Dashboard > Store settings</strong>', 'dropshipping-woocommerce' ),
								__('click to see the image', 'dropshipping-woocommerce' )
							);
							?>
						</p>
					</td>
				</tr>

				<tr class="knawat_dropshipwc_row">
					<th scope="row">
						<?php _e( 'Products Batch Size:','dropshipping-woocommerce' ); ?>
					</th>
					<td>
						<input class="product_batch regular-text" name="knawat[product_batch]" type="number" value="<?php echo $product_batch; ?>" min="5" max="100"/>
						<p class="description" id="product_batch-description">
							<?php
							_e( 'Products batch size for import products from knawat.com', 'dropshipping-woocommerce' );
							?>
						</p>
					</td>
				</tr>

				<tr class="knawat_dropshipwc_row">
					<th scope="row">
						<?php _e( 'Orders Synchronization Interval:','dropshipping-woocommerce' ); ?>
					</th>
					<?php
					$current_interval = get_option('knawat_order_pull_cron_interval', 6 * 60 * 60 );
					$intervals = array(
						172800 => __( '48 Hours','dropshipping-woocommerce' ),
						86400 => __( '24 Hours','dropshipping-woocommerce' ),
						43200 => __( '12 Hours','dropshipping-woocommerce' ),
						32400 => __( '9 Hours','dropshipping-woocommerce' ),
						21600 => __( '6 Hours','dropshipping-woocommerce' ),
						10800 => __( '3 Hours','dropshipping-woocommerce' ),
						3600 => __( '1 Hour','dropshipping-woocommerce' )
					);
					?>
					<td>
						<select name="order_pull_interval" required="required">
						<?php
						foreach ($intervals as $value => $interval) {
							echo '<option value="'.$value.'" '.selected( $current_interval, $value, false ).'>'.$interval.'</option>';
						}
						?>
						</select>
						<p class="description" id="order_pull_interval-description">
							<?php
							_e( 'Select interval for synchronization orders with Knawat', 'dropshipping-woocommerce' );
							?>
						</p>
					</td>
				</tr>

				<?php if( knawat_dropshipwc_is_dokan_active() ) { ?>
					<tr class="knawat_dropshipwc_row">
						<th scope="row">
							<?php _e( 'Dokan Seller','dropshipping-woocommerce' ); ?>
						</th>
						<td>
							<?php
							$seller_args = array(
								'name'		=> 'knawat[dokan_seller]',
								'id'		=> 'knawat_dokan_seller',
								'class'		=> 'dokan_seller',
								'role'		=> 'seller',
								'selected'	=> $dokan_seller,
								'show_option_none' => __( 'Select Dokan Seller','dropshipping-woocommerce' )
							);
							wp_dropdown_users( $seller_args );
							?>
							<p class="description" id="dokan_seller-description">
								<?php
								_e( 'Select dokan seller for import Knawat products under it.', 'dropshipping-woocommerce' );
								?>
							</p>
						</td>
					</tr>
				<?php } ?>

				<?php if( $mp_consumer_key !='' && $mp_consumer_secret != '' ){ ?>
					<tr class="knawat_dropshipwc_row">
						<th scope="row">
							<?php _e( 'Knawat Connection status','dropshipping-woocommerce' ); ?>
						</th>
						<td>
							<?php
							if( 'valid' === $token_status ){
								?>
								<p class="connection_wrap success">
									<span class="dashicons dashicons-yes"></span> <?php _e( 'Connected','dropshipping-woocommerce' ); ?>
								</p>
								<?php
							}else{
								?>
								<p class="connection_wrap error">
									<span class="dashicons dashicons-dismiss"></span> <?php _e( 'Not connected','dropshipping-woocommerce' ); ?>
								</p>
								<p class="description">
									<?php
									_e('Please verify your knawat consumer keys.', 'dropshipping-woocommerce' );
									?>
								</p>
								<?php
							}
							?>
						</td>
					</tr>
				<?php } ?>

			</tbody>
		</table>
		<div class="knawatds_element submit_button">
		    <input type="hidden" name="knawatds_action" value="knawatds_save_settings" />
		    <?php wp_nonce_field( 'knawatds_setting_form_nonce_action', 'knawatds_setting_form_nonce' ); ?>
		    <input type="submit" class="button-primary knawatds_submit_button" style=""  value="<?php esc_attr_e( 'Save Settings', 'dropshipping-woocommerce' ); ?>" />
		</div>
	</form>
</div>