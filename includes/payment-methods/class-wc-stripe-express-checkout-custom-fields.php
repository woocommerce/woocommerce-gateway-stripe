<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

class WC_Stripe_Express_Checkout_Custom_Fields {

	// Constants.
	const ECE_ADDITIONAL_CHECKOUT_FIELD_ID = 'wc-stripe/ece-custom-checkout-data';

	/**
	 * Perform necessary setup steps for supporting custom classic checkout fields
	 * when using express checkout.
	 *
	 * This includes registering the additional checkout field -- the space for holding
	 * custom checkout field data -- and hooking into the actions that will let us
	 * validate and process the data.
	 *
	 * @return void
	 */
	public function init() {
		// Register an additional checkout field, for holding custom checkout field data
		// for ECE on classic checkout.
		woocommerce_register_additional_checkout_field(
			[
				'id'                         => self::ECE_ADDITIONAL_CHECKOUT_FIELD_ID,
				'label'                      => 'Custom checkout fields for express checkout',
				'optionalLabel'              => '',
				'location'                   => 'order',
				'type'                       => 'text',
				'required'                   => false,
				'hidden'                     => [
					'type' => 'object', // Always hide.
				],
				'show_in_order_confirmation' => false,
			],
		);

		// Validate custom checkout data.
		add_action(
			'woocommerce_validate_additional_field',
			[ $this, 'validate_custom_checkout_data' ],
			10,
			3
		);

		// Update order based on custom checkout data.
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this, 'process_custom_checkout_data' ],
			10,
			2
		);

		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this, 'cleanup' ],
			11,
			2
		);
	}

	/**
	 * Cleanup: delete the space we used for other custom checkout fields --
	 * we do not want it persisted separately in the order meta.
	 *
	 * @param WC_Order $order The order to cleanup.
	 * @param WP_REST_Request $request The request object.
	 * @return void
	 */
	public function cleanup( $order, $request ) {
		// Bail if there is no custom checkout data.
		$custom_checkout_data = $this->get_custom_checkout_data_from_request( $request );
		if ( empty( $custom_checkout_data ) ) {
			return;
		}

		$meta_key = CheckoutFields::OTHER_FIELDS_PREFIX . self::ECE_ADDITIONAL_CHECKOUT_FIELD_ID;
		$order->delete_meta_data( $meta_key );
		$order->save();
	}

	/**
	 * Retrieve list of custom checkout fields.
	 *
	 * @param boolean $is_block_checkout Whether we are retrieving for a block checkout page.
	 * @return array Custom checkout fields.
	 */
	public function get_custom_checkout_fields( $is_block_checkout = false ) {
		// Block checkout page
		if ( $is_block_checkout ) {
			try {
				$checkout_fields = Package::container()->get( CheckoutFields::class );
				if ( ! $checkout_fields instanceof CheckoutFields ) {
					return [];
				}

				$custom_checkout_fields = [];
				$additional_fields      = $checkout_fields->get_additional_fields();
				foreach ( $additional_fields as $field_key => $field ) {
					$location                             = $checkout_fields->get_field_location( $field_key );
					$custom_checkout_fields[ $field_key ] = [
						'label'    => $field['label'],
						'type'     => $field['type'],
						'location' => $location,
						'required' => $field['required'],
					];
				}

				return $custom_checkout_fields;
			} catch ( Exception $e ) {
				return [];
			}
		}

		// Classic checkout page
		$custom_checkout_fields   = [];
		$standard_checkout_fields = $this->get_standard_classic_checkout_fields();
		$all_fields               = WC()->checkout()->get_checkout_fields();
		foreach ( $all_fields as $fieldset => $fields ) {
			foreach ( $fields as $field_key => $field ) {
				if ( in_array( $field_key, $standard_checkout_fields, true ) ) {
					continue;
				}

				$custom_checkout_fields[ $field_key ] = [
					'label'    => $field['label'],
					'type'     => $field['type'],
					'location' => $fieldset,
					'required' => $field['required'],
				];
			}
		}

		return $custom_checkout_fields;
	}

	/**
	 * Get standard classic checkout fields.
	 *
	 * @return array Standard classic checkout fields.
	 */
	private function get_standard_classic_checkout_fields() {
		$default_address_fields  = WC()->countries->get_default_address_fields();
		$standard_billing_fields = array_map(
			function ( $field ) {
				return 'billing_' . $field;
			},
			array_keys( $default_address_fields )
		);

		$standard_shipping_fields = array_map(
			function ( $field ) {
				return 'shipping_' . $field;
			},
			array_keys( $default_address_fields )
		);

		$standard_checkout_fields = array_merge(
			$standard_billing_fields,
			$standard_shipping_fields,
			[ 'billing_phone', 'billing_email', 'order_comments' ]
		);

		return $standard_checkout_fields;
	}

	/**
	 * Get custom checkout data from the request object.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array Custom checkout data.
	 */
	private function get_custom_checkout_data_from_request( $request ) {
		$additional_fields         = $request->get_param( 'additional_fields' );
		$custom_checkout_data_json = $additional_fields[ self::ECE_ADDITIONAL_CHECKOUT_FIELD_ID ] ?? '';
		if ( empty( $custom_checkout_data_json ) ) {
			return [];
		}

		return $this->get_custom_checkout_data_from_json( $custom_checkout_data_json );
	}

	/**
	 * Get custom checkout data from a JSON string.
	 *
	 * @param string $custom_checkout_data_json The JSON string.
	 * @return array Custom checkout data.
	 */
	private function get_custom_checkout_data_from_json( $custom_checkout_data_json ) {
		$custom_checkout_data = json_decode( $custom_checkout_data_json, true );
		if ( empty( $custom_checkout_data ) || ! is_array( $custom_checkout_data ) ) {
			return [];
		}

		// Perform basic sanitization before passing to actions.
		$sanitized_custom_checkout_data = [];
		$custom_checkout_fields         = $this->get_custom_checkout_fields();
		foreach ( $custom_checkout_data as $key => $value ) {
			$field_type                                       = $custom_checkout_fields[ $key ]['type'] ?? '';
			$sanitized_key                                    = sanitize_text_field( $key );
			$sanitized_value                                  = $this->get_sanitized_field_value( $value, $field_type );
			$sanitized_custom_checkout_data[ $sanitized_key ] = $sanitized_value;
		}

		return $sanitized_custom_checkout_data;
	}

	/**
	 * Perform basic sanitization on custom checkout field values, based on the field type.
	 *
	 * @param string $value The value to sanitize.
	 * @param string $type The type of the field.
	 * @return mixed The sanitized value.
	 */
	private function get_sanitized_field_value( $value, $type ) {
		if ( '' === $value ) {
			return '';
		}

		switch ( $type ) {
			case 'checkbox':
				return empty( $value ) ? $value : 1;
			case 'multiselect':
				return implode( ', ', wc_clean( $value ) );
			case 'textarea':
				return wc_sanitize_textarea( $value );
			case 'email':
				return sanitize_email( $value );
			default:
				return wc_clean( $value );
		}
	}

	/**
	 * Allow third-party plugins to validate custom checkout data for express checkout orders.
	 *
	 * @param WP_Error $errors The WP_Error object, for adding errors when validation fails.
	 * @param string $field_id The ID of the field.
	 * @param string $field_value The value of the field.
	 */
	public function validate_custom_checkout_data( $errors, $field_id, $field_value ) {
		if ( self::ECE_ADDITIONAL_CHECKOUT_FIELD_ID !== $field_id ) {
			return;
		}

		$custom_checkout_data = $this->get_custom_checkout_data_from_json( $field_value );

		// Enforce required fields.
		$required_field_errors  = [];
		$custom_checkout_fields = $this->get_custom_checkout_fields();
		foreach ( $custom_checkout_fields as $key => $field ) {
			if ( $field['required'] && empty( $custom_checkout_data[ $key ] ) ) {
				$required_field_errors[] = sprintf(
					/* translators: %s: field name */
					__( '%s is a required field.', 'woocommerce-gateway-stripe' ),
					$field['label']
				);
			}
		}

		if ( ! empty( $required_field_errors ) ) {
			$errors->add(
				'woocommerce_invalid_checkout_field',
				implode( "\n", $required_field_errors )
			);
			return;
		}

		/**
		 * Allow third-party plugins to validate custom checkout data for express checkout orders.
		 *
		 * To be used as a stand-in for the `woocommerce_after_checkout_validation` action.
		 *
		 * @since 9.6.0
		 *
		 * @param array $custom_checkout_data The custom checkout data.
		 * @param WP_Error $errors The WP_Error object, for adding errors when validation fails.
		 */
		$validation_errors = new WP_Error();
		do_action( 'wc_stripe_express_checkout_after_checkout_validation', $custom_checkout_data, $validation_errors );

		if ( $validation_errors->has_errors() ) {
			$error_messages = implode( "\n", $validation_errors->get_error_messages() );
			$errors->add(
				'woocommerce_invalid_checkout_field',
				$error_messages
			);
		}
	}

	/**
	 * Allow third-party to add custom classic checkout data to express checkout orders.
	 *
	 * @param WC_Order $order The order to add custom checkout data to.
	 * @param WP_REST_Request $request The request object.
	 * @return void
	 */
	public function process_custom_checkout_data( $order, $request ) {
		$custom_checkout_data = $this->get_custom_checkout_data_from_request( $request );
		if ( empty( $custom_checkout_data ) ) {
			return;
		}

		/**
		 * Allow third-party plugins to add custom checkout data for express checkout orders.
		 *
		 * To be used as a stand-in for the `woocommerce_checkout_update_order_meta` action.
		 *
		 * @since 9.6.0
		 *
		 * @param integer $order_id The order ID.
		 * @param array $custom_checkout_data The custom checkout data.
		 */
		do_action( 'wc_stripe_express_checkout_update_order_meta', $order->get_id(), $custom_checkout_data );
	}
}
