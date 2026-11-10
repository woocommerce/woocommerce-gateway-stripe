<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prevent Stripe gateway credentials from unintended access.
 *
 * @since 11.2.0
 */
class WC_Stripe_REST_Gateway_Settings_Protection {

	/**
	 * Written in place of a withheld value. Sent back on write, it means "leave
	 * unchanged" for secret fields, so a read/edit/write round-trip cannot wipe a secret.
	 * It will be written as-is for public fields.
	 *
	 * @var string
	 */
	public const WITHHELD_PLACEHOLDER = '__wc_stripe_setting_withheld__';

	/**
	 * Types that only hold a declared value, so they are never free-form
	 * credentials even when the field ID matches the secret pattern.
	 *
	 * @var string[]
	 */
	private const NON_SECRET_TYPES = [ 'checkbox', 'select', 'multiselect', 'radio', 'number', 'image_width', 'title' ];

	/**
	 * Fields that are known to hold a secret value.
	 *
	 * @var string[]
	 */
	private const KNOWN_SECRET_FIELDS = [
		'api_credentials',
		'secret_key',
		'test_secret_key',
		'webhook_secret',
		'test_webhook_secret',
	];

	/**
	 * Registers the read and write hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Read: both the wc/v2 and wc/v3 payment-gateway controllers fire this.
		add_filter( 'woocommerce_rest_prepare_payment_gateway', [ $this, 'withhold_secret_values' ], 100, 2 );
		// Write: restore a secret sent back as the placeholder before it is saved.
		add_filter( 'woocommerce_gateway_stripe_settings_values', [ $this, 'restore_withheld_secret_values' ], 100, 2 );
	}

	/**
	 * Replaces each credential value with the placeholder, unless the request may view them.
	 *
	 * @param WP_REST_Response   $response The response object.
	 * @param WC_Payment_Gateway $gateway  The gateway being serialized.
	 * @return WP_REST_Response
	 */
	public function withhold_secret_values( $response, $gateway ) {
		if ( ! $response instanceof WP_REST_Response || ! $gateway instanceof WC_Stripe_Payment_Gateway ) {
			return $response;
		}

		if ( $this->can_view_secret_values_via_rest() ) {
			return $response;
		}

		$data = $response->get_data();
		if ( empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return $response;
		}

		$fields = $gateway->get_form_fields();

		foreach ( $data['settings'] as $id => $setting ) {
			if ( ! is_array( $setting ) || '' === ( $setting['value'] ?? '' ) ) {
				continue;
			}
			// Decide from the form field, not this REST row, so the write path makes the same decision.
			if ( ! $this->is_secret_field( (string) $id, self::get_form_field( $fields, (string) $id ), $gateway ) ) {
				continue;
			}
			$data['settings'][ $id ]['value']          = self::WITHHELD_PLACEHOLDER;
			$data['settings'][ $id ]['value_withheld'] = true;
		}

		$response->set_data( $data );
		return $response;
	}

	/**
	 * Restores the stored value when a field is saved as the placeholder.
	 *
	 * @param array              $settings Settings about to be saved.
	 * @param WC_Payment_Gateway $gateway  The gateway being saved.
	 * @return array
	 */
	public function restore_withheld_secret_values( $settings, $gateway ) {
		if ( ! is_array( $settings ) || ! $gateway instanceof WC_Stripe_Payment_Gateway ) {
			return $settings;
		}

		// Nothing to restore unless the placeholder is actually present.
		if ( ! in_array( self::WITHHELD_PLACEHOLDER, $settings, true ) ) {
			return $settings;
		}

		$stored = get_option( $gateway->get_option_key(), [] );
		$stored = is_array( $stored ) ? $stored : [];
		$fields = $gateway->get_form_fields();

		foreach ( $settings as $id => $value ) {
			if ( self::WITHHELD_PLACEHOLDER !== $value ) {
				continue;
			}

			// The placeholder only stands for a value withheld on read; any other field is saved as sent.
			if ( $this->is_secret_field( (string) $id, self::get_form_field( $fields, (string) $id ), $gateway ) ) {
				$settings[ $id ] = $stored[ $id ] ?? '';
				// The REST controller sets $gateway->settings before this filter runs and builds its
				// response from it, so restore it too; otherwise the response and the rest of the
				// request see the placeholder.
				if ( is_array( $gateway->settings ) ) {
					$gateway->settings[ $id ] = $settings[ $id ];
				}
			}
		}

		return $settings;
	}

	/**
	 * Returns a field definition from $fields, or an empty array.
	 *
	 * @param array  $fields The gateway form fields.
	 * @param string $id     The field ID.
	 * @return array The field definition, or an empty array when the field is not defined.
	 */
	private static function get_form_field( array $fields, string $id ): array {
		if ( is_array( $fields[ $id ] ?? null ) ) {
			return $fields[ $id ];
		}
		return [];
	}

	/**
	 * Whether a settings field holds a credential: a password type, or an ID
	 * matching the secret pattern, unless the type only holds a declared value.
	 *
	 * @param string                    $id         The field ID.
	 * @param array                     $form_field The field definition from get_form_fields() (`type`, `title`, ...). It never contains the stored value.
	 * @param WC_Stripe_Payment_Gateway $gateway    The gateway.
	 * @return bool
	 */
	private function is_secret_field( string $id, array $form_field, WC_Stripe_Payment_Gateway $gateway ): bool {
		$type = $form_field['type'] ?? '';

		if ( in_array( $type, self::NON_SECRET_TYPES, true ) ) {
			$is_secret = false;
		} else {
			$is_secret = 'password' === $type
				|| in_array( $id, self::KNOWN_SECRET_FIELDS, true )
				|| 1 === preg_match( '/secret|password|token|api[_-]?key|signature|private/i', $id );
		}

		/**
		 * Filters whether a Stripe gateway settings field is treated as a secret
		 * and withheld from REST reads.
		 *
		 * @param bool                      $is_secret  Whether the field is a secret and should be withheld.
		 * @param string                    $id         The field ID.
		 * @param array                     $form_field The field definition from get_form_fields() (`type`, `title`, ...), the same on read and write. It never contains the stored value.
		 * @param WC_Stripe_Payment_Gateway $gateway    The gateway.
		 *
		 * @since 11.2.0
		 */
		return (bool) apply_filters( 'wc_stripe_rest_withhold_secret_setting', $is_secret, $id, $form_field, $gateway );
	}

	/**
	 * Whether the request may view secret values: a login cookie checked with a REST nonce,
	 * from a user who can also edit the gateway settings. API keys, application passwords,
	 * and internal requests never qualify, so a read-only credential cannot read a secret.
	 *
	 * Core sets $wp_rest_auth_cookie to true only when the login cookie authenticated the
	 * request, so an API-key request sent with another user's cookie does not qualify. The
	 * nonce check also fails then, because a nonce is tied to the current user. Do not call
	 * wp_validate_auth_cookie() here: it sets that global to true for any valid cookie.
	 *
	 * @return bool
	 */
	protected function can_view_secret_values_via_rest(): bool {
		global $wp_rest_auth_cookie;

		if ( ! is_user_logged_in() || true !== $wp_rest_auth_cookie ) {
			return false;
		}

		// Same nonce sources as rest_cookie_check_errors().
		$nonce = $_REQUEST['_wpnonce'] ?? $_SERVER['HTTP_X_WP_NONCE'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Recommended
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'wp_rest' ) ) {
			return false;
		}

		return (bool) wc_rest_check_manager_permissions( 'payment_gateways', 'edit' );
	}
}
