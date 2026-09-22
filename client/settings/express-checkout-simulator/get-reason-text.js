import { __, sprintf } from '@wordpress/i18n';
import { PAYMENT_METHOD_UNAVAILABLE_REASONS } from 'wcstripe/stripe-utils/constants';

/**
 * Maps a method-level unavailability reason to a concise, human-readable sentence for the
 * simulator. Wording mirrors the unavailable pills shown on the main settings page
 * (`PaymentMethodMissingCurrencyPill`, `PaymentMethodUnavailableDueTaxSetupPill`,
 * `PaymentMethodRequiresCardMethodPill`) so the explanation a merchant sees is consistent
 * across surfaces.
 *
 * @param {string|null} reason       One of `PAYMENT_METHOD_UNAVAILABLE_REASONS`, or null.
 * @param {string}      methodLabel  Human label for the method (e.g. "Amazon Pay").
 * @param {string[]}    [currencies] Supported currency codes; used for the currency reason.
 * @return {string|null} The reason sentence, or null when the method is not blocked.
 */
const getReasonText = ( reason, methodLabel, currencies = [] ) => {
	switch ( reason ) {
		case PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY:
			return sprintf(
				/* translators: %1$s: payment method name. %2$s: comma-separated currency codes. */
				__(
					'%1$s requires the store currency to be set to %2$s.',
					'woocommerce-gateway-stripe'
				),
				methodLabel,
				currencies.join( ', ' )
			);
		case PAYMENT_METHOD_UNAVAILABLE_REASONS.TAX_BASED_ON_BILLING_ADDRESS:
			return sprintf(
				/* translators: %1$s: payment method name. */
				__(
					"%1$s is unavailable because the store tax setup is based on the customer's billing address, which isn't known before payment.",
					'woocommerce-gateway-stripe'
				),
				methodLabel
			);
		case PAYMENT_METHOD_UNAVAILABLE_REASONS.REQUIRES_CARD_METHOD:
			return sprintf(
				/* translators: %1$s: payment method name. */
				__(
					'The credit card / debit card payment method must be enabled in order to use %1$s.',
					'woocommerce-gateway-stripe'
				),
				methodLabel
			);
		default:
			return null;
	}
};

export default getReasonText;
