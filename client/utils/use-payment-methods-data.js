import { useAccount } from 'wcstripe/data/account';
import paymentMethodsMap from 'payment-methods-map';

export const usePaymentMethodsData = () => {
	const { data } = useAccount();

	// If the account country is GB, we need to update the Afterpay payment method data with Clearpay.
	if ( data?.account?.country === 'GB' ) {
		return {
			...paymentMethodsMap,
			afterpay_clearpay: {
				...paymentMethodsMap.afterpay_clearpay,
				label: paymentMethodsMap.afterpay_clearpay.labelClearpay,
				description:
					paymentMethodsMap.afterpay_clearpay.descriptionClearpay,
				Icon: paymentMethodsMap.afterpay_clearpay.IconClearpay,
			},
		};
	}
	return paymentMethodsMap;
};

export default usePaymentMethodsData;
