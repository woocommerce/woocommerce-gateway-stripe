<?php
/**
 * Class WC_Stripe_Failed_Refund_Email_Additional_Content_Update
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clears the failed order default text saved in the Stripe refund failed emails.
 *
 * These emails extend WC_Email_Failed_Order and used to inherit its default "Additional content".
 * WooCommerce prefills the settings field with that default, so saving the email settings stored
 * it. The templates never rendered the field, so the text was never sent. Once they render it,
 * stores that saved the settings would send failed order text (written for merchants, about
 * failed payments) in a refund failed email, including to customers.
 *
 * @since x.x.x
 */
class WC_Stripe_Failed_Refund_Email_Additional_Content_Update {

	/**
	 * Option flag used to ensure the migration only runs once.
	 */
	private const MIGRATION_FLAG_OPTION = 'wc_stripe_failed_refund_email_additional_content_cleared';

	/**
	 * Settings options of the Stripe refund failed emails.
	 */
	private const EMAIL_SETTINGS_OPTIONS = [
		'woocommerce_wc_stripe_failed_refund_admin_settings',
		'woocommerce_wc_stripe_failed_refund_customer_settings',
	];

	/**
	 * Clears the saved text only when it is exactly a failed order default, so text the merchant wrote is kept.
	 *
	 * @param string|false $previous_version The plugin version recorded before this upgrade, or false on a new install.
	 * @return void
	 */
	public function maybe_migrate( $previous_version = false ): void {
		if ( 'yes' === get_option( self::MIGRATION_FLAG_OPTION ) ) {
			return;
		}

		if ( false !== $previous_version ) {
			$defaults = self::get_failed_order_defaults();

			foreach ( self::EMAIL_SETTINGS_OPTIONS as $option_name ) {
				$settings = get_option( $option_name );
				if ( ! is_array( $settings ) || ! isset( $settings['additional_content'] ) || ! is_string( $settings['additional_content'] ) ) {
					continue;
				}

				if ( ! in_array( trim( $settings['additional_content'] ), $defaults, true ) ) {
					continue;
				}

				$settings['additional_content'] = '';
				update_option( $option_name, $settings );
			}
		}

		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );
	}

	/**
	 * Returns the two WC_Email_Failed_Order defaults (email improvements on and off), in English and in the site language.
	 *
	 * The strings are copied rather than read from a WC_Email_Failed_Order instance, because creating one registers
	 * its order status hooks again and would send duplicate failed order emails for the rest of the request.
	 *
	 * @return string[]
	 */
	private static function get_failed_order_defaults(): array {
		$strings = [
			'Hopefully they’ll be back. Read more about <a href="https://woocommerce.com/document/managing-orders/">troubleshooting failed payments</a>.',
			'We hope they’ll be back soon! Read more about <a href="https://woocommerce.com/document/managing-orders/">troubleshooting failed payments</a>.',
		];

		$defaults = $strings;
		foreach ( $strings as $string ) {
			// The saved value was translated with WooCommerce's text domain, so look it up there.
			$defaults[] = __( $string, 'woocommerce' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.TextDomainMismatch
		}

		return array_values( array_unique( $defaults ) );
	}
}
