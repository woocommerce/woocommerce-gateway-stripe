<?php
class WC_Stripe_Privacy extends WC_Extensions_Privacy {
	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct( 'Stripe' );

		$this->add_exporter( __( 'WooCommerce Stripe Order Data', 'woocommerce-gateway-stripe' ), array( $this, 'order_data_exporter' ) );
		$this->add_exporter( __( 'WooCommerce Stripe Customer Data', 'woocommerce-gateway-stripe' ), array( $this, 'customer_data_exporter' ) );
	}

	/**
	 * Gets the message of the privacy to display.
	 *
	 */
	public function get_message() {
		return wpautop( sprintf( __( 'This extension handles personal data. To learn more, please review this extension\'s <a href="%s" target="_blank">privacy policy</a>.', 'woocommerce-gateway-stripe' ), 'https://docs.woocommerce.com/privacy/?woocommerce-gateway-stripe' ) );
	}

	/**
	 * Gets the message of the privacy to display for Stripe.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function order_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$page           = (int) $page;
		$user           = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.
		$data_to_export = array();

		$order_query    = array(
			'payment_method' => 'stripe',
			'limit'          => 10,
			'page'           => $page,
		);

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = (int) $user->ID;
		} else {
			$order_query['billing_email'] = $email_address;
		}

		$orders = wc_get_orders( $order_query );

		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_orders',
					'group_label' => __( 'Orders', 'woocommerce-gateway-stripe' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'Stripe token', 'woocommerce-gateway-stripe' ),
							'value' => get_post_meta( $order->get_id(), '_stripe_source_id', true ),
						),
					),
				);
			}

			$done = 10 > count( $orders );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Finds and exports customer data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public function customer_data_exporter( $email_address, $page ) {
		$user           = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.
		$data_to_export = array();

		if ( $user instanceof WP_User ) {
			$stripe_user = new WC_Stripe_Customer( $user->ID );

			$data_to_export[] = array(
				'group_id'    => 'woocommerce_customer',
				'group_label' => __( 'Customer Data', 'woocommerce-gateway-stripe' ),
				'item_id'     => 'user',
				'data'        => array(
					array(
						'name'  => __( 'Stripe token', 'woocommerce-gateway-stripe' ),
						'value' => $stripe_user->get_id(),
					),
				),
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}
}

new WC_Stripe_Privacy();
