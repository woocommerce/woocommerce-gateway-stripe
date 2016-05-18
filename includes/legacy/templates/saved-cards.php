<h2 id="saved-cards" style="margin-top:40px;"><?php _e( 'Saved cards', 'woocommerce-gateway-stripe' ); ?></h2>
<table class="shop_table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Card', 'woocommerce-gateway-stripe' ); ?></th>
			<th><?php esc_html_e( 'Expires', 'woocommerce-gateway-stripe' ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $cards as $card ) :
			if ( 'card' !== $card->object ) {
				continue;
			}

			$is_default_card = $card->id === $default_card ? true : false;
		?>
		<tr>
            <td><?php printf( __( '%s card ending in %s', 'woocommerce-gateway-stripe' ), $card->brand, $card->last4 ); ?>
            	<?php if ( $is_default_card ) echo '<br />' . __( '(Default)', 'woocommerce-gateway-stripe' ); ?>
            </td>
            <td><?php printf( __( 'Expires %s/%s', 'woocommerce-gateway-stripe' ), $card->exp_month, $card->exp_year ); ?></td>
			<td>
                <form action="" method="POST">
                    <?php wp_nonce_field ( 'stripe_del_card' ); ?>
                    <input type="hidden" name="stripe_delete_card" value="<?php echo esc_attr( $card->id ); ?>">
                    <input type="submit" class="button" value="<?php esc_attr_e( 'Delete card', 'woocommerce-gateway-stripe' ); ?>">
                </form>

                <?php if ( ! $is_default_card ) { ?>
	                <form action="" method="POST" style="margin-top:10px;">
	                    <?php wp_nonce_field ( 'stripe_default_card' ); ?>
	                    <input type="hidden" name="stripe_default_card" value="<?php echo esc_attr( $card->id ); ?>">
	                    <input type="submit" class="button" value="<?php esc_attr_e( 'Make Default', 'woocommerce-gateway-stripe' ); ?>">
	                </form>
                <?php } ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
