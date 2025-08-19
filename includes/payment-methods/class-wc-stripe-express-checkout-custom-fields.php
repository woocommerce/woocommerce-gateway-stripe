<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

class WC_Stripe_Express_Checkout_Custom_Fields {
	/**
	 * Perform necessary setup steps for supporting custom checkout fields in express checkout,
	 * including registering space for the data in the Store API,
	 * and hooking into the action that will let us process the data and update the order.
	 *
	 * @return void
	 */
	public function init() {
		// Register our express checkout data as extended data, which will hold
		// custom checkout fielddata if present.
		$extend_schema = StoreApi::container()->get( ExtendSchema::class );
		$extend_schema->register_endpoint_data(
			[
				'endpoint'        => CheckoutSchema::IDENTIFIER,
				'namespace'       => 'wc-stripe/express-checkout',
				'schema_callback' => [ $this, 'get_custom_checkout_data_schema' ],
			]
		);

		// Update order based on extended data.
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this, 'process_custom_checkout_data' ],
			10,
			2
		);
	}

	/**
	 * Allow third-party to validate and add custom checkout data to
	 * express checkout orders for stores using classic, shortcode-based checkout.
	 *
	 * @param WC_Order $order The order to add custom checkout data to.
	 * @param WP_REST_Request $request The request object.
	 * @return void
	 */
	public function process_custom_checkout_data( $order, $request ) {
		$custom_checkout_data = $this->get_custom_checkout_data_from_request( $request );

		// Enforce required fields.
		$required_field_errors  = [];
		$custom_checkout_fields = $this->get_custom_checkout_fields( 'classic' );
		foreach ( $custom_checkout_fields as $key => $field ) {
			if ( $field['required'] && empty( $custom_checkout_data[ $key ] ) ) {
				$required_field_errors[] = sprintf(
					/* translators: %s: field name */
					__( '%s is a required field.', 'woocommerce-gateway-stripe' ),
					empty( $field['label'] ) ? $key : $field['label']
				);
			}
		}

		if ( ! empty( $required_field_errors ) ) {
			$error_messages = implode( "\n", $required_field_errors );
			throw new RouteException( 'wc_stripe_express_checkout_missing_required_fields', $error_messages, 400 );
		}

		$errors = new WP_Error();
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
		do_action( 'wc_stripe_express_checkout_after_checkout_validation', $custom_checkout_data, $errors );

		if ( $errors->has_errors() ) {
			$error_messages = implode( "\n", $errors->get_error_messages() );
			throw new RouteException( 'wc_stripe_express_checkout_invalid_data', $error_messages, 400 );
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

	/**
	 * Get custom checkout data from the request object.
	 *
	 * To support custom fields in express checkout and classic checkout,
	 * we pass custom data as extended data, i.e. under extensions.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array Custom checkout data.
	 */
	private function get_custom_checkout_data_from_request( $request ) {
		$extensions = $request->get_param( 'extensions' );
		if ( empty( $extensions ) ) {
			return [];
		}

		$custom_checkout_data_json = $extensions['wc-stripe/express-checkout']['custom_checkout_data'] ?? '';
		if ( empty( $custom_checkout_data_json ) ) {
			return [];
		}

		$custom_checkout_data = json_decode( $custom_checkout_data_json, true );
		if ( empty( $custom_checkout_data ) || ! is_array( $custom_checkout_data ) ) {
			return [];
		}

		// Perform basic sanitization before passing to actions.
		$sanitized_custom_checkout_data = [];
		$custom_checkout_fields         = $this->get_custom_checkout_fields( 'classic' );
		foreach ( $custom_checkout_data as $key => $value ) {
			$field_type                                       = $custom_checkout_fields[ $key ]['type'] ?? 'text';
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
	 * @return mixed The sanitized field value.
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
	 * Get custom checkout data schema.
	 *
	 * @return array Custom checkout data schema.
	 */
	public function get_custom_checkout_data_schema() {
		return [
			'custom_checkout_data' => [
				'type'        => [ 'string', 'null' ],
				'context'     => [],
				'arg_options' => [],
			],
		];
	}

	/**
	 * Retrieve custom checkout field IDs.
	 *
	 * @return array Custom checkout field IDs.
	 */
	public function get_custom_checkout_fields( $context = '' ) {
		// Block checkout page
		if ( has_block( 'woocommerce/checkout' ) || 'block' === $context ) {
			try {
				$checkout_fields = Package::container()->get( CheckoutFields::class );
				if ( ! $checkout_fields instanceof CheckoutFields ) {
					return [];
				}

				$block_custom_checkout_fields = [];
				$additional_fields            = $checkout_fields->get_additional_fields();
				foreach ( $additional_fields as $field_key => $field ) {
					$block_custom_checkout_fields[ $field_key ] = [
						'label'    => $field['label'] ?? '',
						'type'     => $field['type'] ?? 'text',
						'location' => $checkout_fields->get_field_location( $field_key ),
						'required' => $field['required'] ?? false,
					];
				}

				return $block_custom_checkout_fields;
			} catch ( Exception $e ) {
				return [];
			}
		}

		// Classic checkout page
		if ( is_checkout() || 'classic' === $context ) {
			$classic_custom_checkout_fields = [];
			$standard_checkout_fields       = $this->get_standard_checkout_fields();
			$all_fields                     = WC()->checkout()->get_checkout_fields();
			foreach ( $all_fields as $fieldset => $fields ) {
				foreach ( $fields as $field_key => $field ) {
					if ( in_array( $field_key, $standard_checkout_fields, true ) ) {
						continue;
					}

					$classic_custom_checkout_fields[ $field_key ] = [
						'label'    => $field['label'] ?? '',
						'type'     => $field['type'] ?? 'text',
						'location' => $fieldset,
						'required' => $field['required'] ?? false,
					];
				}
			}

			return $classic_custom_checkout_fields;
		}

		if ( is_cart() || is_product() ) {
			return array_merge(
				$this->get_custom_checkout_fields( 'block' ),
				$this->get_custom_checkout_fields( 'classic' )
			);
		}

		return [];
	}

	/**
	 * Get standard checkout fields.
	 *
	 * @return array Standard checkout fields.
	 */
	private function get_standard_checkout_fields() {
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
}
