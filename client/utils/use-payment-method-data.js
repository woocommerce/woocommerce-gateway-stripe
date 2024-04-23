import { useAccount } from 'wcstripe/data/account';
import paymentMethodsMap from 'payment-methods-map';

export const usePaymentMethodData = ( name ) => {
	const { data } = useAccount();

	const paymentMethod = paymentMethodsMap[ name ];
	if ( data?.account?.country === 'GB' && name === 'afterpay_clearpay' ) {
		const {
			labelClearpay,
			descriptionClearpay,
			IconClearpay,
		} = paymentMethod;
		return {
			label: labelClearpay,
			description: descriptionClearpay,
			Icon: IconClearpay,
			...paymentMethod,
		};
	}
	return paymentMethod;
};

export default usePaymentMethodData;
