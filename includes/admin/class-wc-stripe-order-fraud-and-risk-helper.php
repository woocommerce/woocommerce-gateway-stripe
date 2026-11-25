<?php

abstract class WC_Stripe_Order_Fraud_And_Risk_Helper {
	public const STRIPE_CHARGE_FRAUD_FLAGS_META_NAME = 'stripe_charge_fraud_and_risk_flags';

	private const STRIPE_CHARGE_FRAUD_FLAGS_PROPERTIES = [
		'outcome.risk_level',
		'outcome.risk_score',
		'outcome.type',
		'outcome.reason',
		'fraud_details',
		'payment_method_details.card.checks',
		'payment_method_details.card.three_d_secure',
	];

	/**
	 * Copies a nested object property to a destination array using the provided path.
	 *
	 * @since x.x.x
	 * @param object $src_object      The source object.
	 * @param array  $dst_array       The destination array.
	 * @param array  $path_components The path.
	 * @return void
	 */
	private static function create_array_value_from_object( $src_object, &$dst_array, $path_components ): void {
		$path_valid          = [];
		$path_valid_value    = null;
		$num_path_components = count( $path_components );

		$ref = $src_object;

		for ( $i = 0; $i < $num_path_components; $i++ ) {
			$path_component = $path_components[ $i ];
			if ( ! property_exists( $ref, $path_component ) ) {
				$path_valid       = [];
				$path_valid_value = null;
				break;
			}

			$path_valid[] = $path_component;

			if ( $num_path_components - 1 === $i ) {
				$path_valid_value = $ref->{$path_component};
				break;
			}

			$ref = $ref->{$path_component};
		}

		if ( count( $path_valid ) === $num_path_components ) {
			$ref                       = &$dst_array;
			$num_path_valid_components = count( $path_valid );
			for ( $i = 0; $i < $num_path_valid_components; $i++ ) {
				$path_valid_component = $path_valid[ $i ];

				if ( $num_path_valid_components - 1 === $i ) {
					$ref[ $path_valid_component ] = $path_valid_value;
					break;
				}

				$ref = &$ref[ $path_valid_component ];
			}
		}
	}

	/**
	 * Sets an order fraud and risk meta from a charge object.
	 *
	 * @since x.x.x
	 * @param WC_Order $order  The order.
	 * @param StdClass $charge The charge.
	 * @return void
	 */
	public static function set_order_transaction_fraud_flags( $order, $charge ): void {
		$fraud_flags_meta = [
			'charge_id' => $charge->id,
		];

		foreach ( self::STRIPE_CHARGE_FRAUD_FLAGS_PROPERTIES as $fraud_flags_property ) {
			$path_components = explode( '.', $fraud_flags_property );
			self::create_array_value_from_object( $charge, $fraud_flags_meta, $path_components );
		}

		$order->update_meta_data( self::STRIPE_CHARGE_FRAUD_FLAGS_META_NAME, (string) wp_json_encode( $fraud_flags_meta ) );
		$order->save();
	}

	/**
	 * Return an order fraud and risk flags.
	 *
	 * @since x.x.x
	 * @param WC_Order $order  The order.
	 * @return object|null
	 */
	public static function get_order_transaction_fraud_flags( $order ) {
		$fraud_flags    = null;
		$transaction_id = $order->get_transaction_id();

		if ( ! $transaction_id ) {
			return $fraud_flags;
		}

		if ( $order->meta_exists( self::STRIPE_CHARGE_FRAUD_FLAGS_META_NAME ) ) {
			$fraud_flags_as_json = $order->get_meta( self::STRIPE_CHARGE_FRAUD_FLAGS_META_NAME, true );
			$fraud_flags         = json_decode( $fraud_flags_as_json );
		}

		if ( is_null( $fraud_flags ) || $fraud_flags->charge_id !== $transaction_id ) {
			$charge = self::get_order_transaction_by_id( $transaction_id );

			if ( $charge ) {
				self::set_order_transaction_fraud_flags( $order, $charge );
				$fraud_flags = $order->get_meta( self::STRIPE_CHARGE_FRAUD_FLAGS_META_NAME, true );
			}
		}

		return $fraud_flags;
	}

	/**
	 * Return an order charge.
	 *
	 * @since x.x.x
	 * @param string $charge_id  The transaction id.
	 * @return object|null
	 */
	public static function get_order_transaction_by_id( $charge_id ) {
		$charge = WC_Stripe_API::retrieve( 'charges/as' . $charge_id );

		if ( ! is_object( $charge ) || ! empty( $charge->error ) || is_wp_error( $charge ) ) {
			return null;
		}

		return $charge;
	}

	public static function order_has_transaction_fraud_flags_risk( $order ) {
		$fraud_flags = self::get_order_transaction_fraud_flags( $order );

		$check = $fraud_flags->outcome->risk_level ?? null;

		if ( ! is_null( $check ) && 'normal' !== $check ) {
			return $fraud_flags;
		}

		$check = $fraud_flags->fraud_details ?? null;

		if ( is_object( $check ) && 0 !== count( get_object_vars( $check ) ) ) {
			return $fraud_flags;
		}

		$check = $fraud_flags->payment_method_details->card->checks->cvc_check ?? null;

		if ( ! is_null( $check ) && 'pass' !== $check ) {
			return $fraud_flags;
		}

		$check = $fraud_flags->payment_method_details->card->checks->address_line1_check ?? null;

		if ( ! is_null( $check ) && 'fail' === $check ) {
			return $fraud_flags;
		}

		$check = $fraud_flags->payment_method_details->card->checks->address_postal_code_check ?? null;

		if ( ! is_null( $check ) && 'fail' === $check ) {
			return $fraud_flags;
		}

		$check = $fraud_flags->payment_method_details->card->three_d_secure->result ?? null;

		if ( ! is_null( $check ) && 'failed' === $check ) {
			return $fraud_flags;
		}

		return false;
	}
}
