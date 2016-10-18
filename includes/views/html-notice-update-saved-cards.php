<?php
/**
 * Admin View: Notice - Stripe Update
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<p><strong><?php _e( 'WooCommerce Stripe Gateway Data Update', 'woocommerce-gateway-stripe' ); ?></strong> &#8211; <?php _e( 'We need to update your store\'s database to the latest version.', 'woocommerce-gateway-stripe' ); ?></p>
<p class="submit"><a href="<?php echo esc_url( $update_action ); ?>" class="wc-stripe-update-now button-primary"><?php _e( 'Run the updater', 'woocommerce-gateway-stripe' ); ?></a></p>

