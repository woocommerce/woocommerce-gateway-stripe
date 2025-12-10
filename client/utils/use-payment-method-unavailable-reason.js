import { getSetting } from '@woocommerce/settings';
import getPaymentMethodUnavailableReason from 'utils/get-payment-method-unavailable-reason';

/**
 * React hook to return the reason why a payment method is unavailable, or null if it is available.
 *
 * @param {string} paymentMethodId The payment method ID.
 * @return {string|null} The reason why the payment method is unavailable, or null if it is available. See `PAYMENT_METHOD_UNAVAILABLE_REASONS` for possible values.
 */
const usePaymentMethodUnavailableReason = ( paymentMethodId ) => {
	const storeCurrencyCode = getSetting( 'currency' )?.code;

	return getPaymentMethodUnavailableReason( {
		paymentMethodId,
		storeCurrencyCode,
	} );
};

export default usePaymentMethodUnavailableReason;
