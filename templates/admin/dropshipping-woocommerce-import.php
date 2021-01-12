<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$consumer_keys = knawat_dropshipwc_get_consumerkeys();
if ( empty( $consumer_keys ) ) {
	?>
	<div class="knawat_dropshipwc_import">
		<!-- Google Tag Manager -->
		<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
		new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
		j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
		'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(window,document,'script','dataLayer','GTM-XXXX');</script>
		<!-- End Google Tag Manager -->
		<!-- Google Tag Manager (noscript) -->
		<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXX"
		height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
		<!-- End Google Tag Manager (noscript) -->
		<p>
			<?php printf( __( 'Please insert Knawat Consumer Key and Consumer Secret in <a href="%s">Settings</a> tab.', 'dropshipping-woocommerce' ), esc_url( add_query_arg( 'tab', 'settings', admin_url( 'admin.php?page=knawat_dropship' ) ) ) ); ?></p>
	</div>
	<?php
} else {
	$batches        = knawat_dropshipwc_get_inprocess_import();
	$knawat_options = knawat_dropshipwc_get_options();
	$token_status   = isset( $knawat_options['token_status'] ) ? esc_attr( $knawat_options['token_status'] ) : 'invalid';
	?>
	<div class="knawat_dropshipwc_import">
		<!-- Google Tag Manager -->
		<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
		new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
		j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
		'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(window,document,'script','dataLayer','GTM-XXXX');</script>
		<!-- End Google Tag Manager -->
		<!-- Google Tag Manager (noscript) -->
		<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXX"
		height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
		<!-- End Google Tag Manager (noscript) -->

		<h3><?php esc_attr_e( 'Product Import', 'dropshipping-woocommerce' ); ?></h3>
		<p><?php _e( 'Plugin auto import/update products from knawat.com in background on regular interval. But, if you want then you can manually start import.', 'dropshipping-woocommerce' ); ?></p>
		<table class="form-table">
			<tbody>
				<?php do_action( 'knawat_dropshipwc_before_settings_section' ); ?>

				<tr class="knawat_dropshipwc_row">
					<th scope="row">
						<?php _e( 'Start product import', 'dropshipping-woocommerce' ); ?>
					</th>
					<td>
						<?php
						if ( empty( $batches ) ) {
							if ( 'valid' === $token_status ) {
								?>
								<a class="button button-primary" href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=knawatds_manual_import' ), 'knawatds_manual_import_action', 'manual_nonce' ); ?>">
									<?php esc_html_e( 'Start Import', 'dropshipping-woocommerce' ); ?>
								</a>
								<?php
							} else {
								?>
								<a class="button button-primary" disabled="disabled">
									<?php esc_html_e( 'Start Import', 'dropshipping-woocommerce' ); ?>
								</a>
								<p class="description">
									<?php _e( 'You\'re not connected to a knawat.com, please make a connection for start import.', 'dropshipping-woocommerce' ); ?>
								</p>
								<?php
							}
						} else {
							$imported = $updated = $failed = 0;
							$batch    = isset( $batches[0]->option_value ) ? maybe_unserialize( $batches[0]->option_value ) : array();

							if ( ! empty( $batch ) && is_array( $batch ) ) {
								$batch    = current( $batch );
								$imported = ! empty( $batch['imported'] ) ? $batch['imported'] : 0;
								$failed   = ! empty( $batch['failed'] ) ? $batch['failed'] : 0;
								$updated  = ! empty( $batch['updated'] ) ? $batch['updated'] : 0;
							}
							?>
							<a class="button button-primary" disabled="disabled">
								<?php esc_html_e( 'Start Import', 'dropshipping-woocommerce' ); ?>
							</a>
							<a class="button" href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=knawatds_stop_import' ), 'knawatds_stop_import_action', 'stop_import_nonce' ); ?>" onclick="return confirm('<?php esc_html_e( 'Are you sure? do you really want to stop import?', 'dropshipping-woocommerce' ); ?>')">
								<?php esc_html_e( 'Stop Import', 'dropshipping-woocommerce' ); ?>
							</a>
							<p class="description">
								<?php _e( 'Product import is In-progress already. you can\'t start import now. Please find current import status below.', 'dropshipping-woocommerce' ); ?>
							</p>
							<p>
								<strong><?php _e( 'Imported:', 'dropshipping-woocommerce' ); ?></strong> <?php printf( __( '%d Products with variations.', 'dropshipping-woocommerce' ), $imported ); ?><br/>
								<strong><?php _e( 'Updated:', 'dropshipping-woocommerce' ); ?></strong> <?php printf( __( '%d Products with variations.', 'dropshipping-woocommerce' ), $updated ); ?><br/>
								<strong><?php _e( 'Failed:', 'dropshipping-woocommerce' ); ?></strong> <?php printf( __( '%d Products with variations.', 'dropshipping-woocommerce' ), $failed ); ?><br/>
							</p>
							<?php
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}
