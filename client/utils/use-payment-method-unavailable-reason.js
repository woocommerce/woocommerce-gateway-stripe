import { getSetting } from '@woocommerce/settings';
import { useContext } from 'react';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import getPaymentMethodUnavailableReason from 'utils/get-payment-method-unavailable-reason';

/**
 * React hook to return the reason why a payment method is unavailable, or null if it is available.
 *
 * @param {string} paymentMethodId The payment method ID.
 * @return {string|null} The reason why the payment method is unavailable, or null if it is available. See `PAYMENT_METHOD_UNAVAILABLE_REASONS` for possible values.
 */
const usePaymentMethodUnavailableReason = ( paymentMethodId ) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	const storeCurrencyCode = getSetting( 'currency' )?.code;

	return getPaymentMethodUnavailableReason( {
		paymentMethodId,
		isUpeEnabled,
		storeCurrencyCode,
	} );
};

export default usePaymentMethodUnavailableReason;
