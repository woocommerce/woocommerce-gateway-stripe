//TODO, these should come from an endpoint/ data store.
const useEnabledPaymentMethodIds = () => {
	return [ [ 'card', 'sepa_debit' ], () => ( {} ) ];
};
const useGetAvailablePaymentMethodIds = () => {
	return [ 'card', 'giropay', 'sofort', 'sepa_debit' ];
};
const useSettings = () => {
	return {
		saveSettings: Promise.resolve( 'Success' ),
		isSaving: false,
	};
};

export {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useSettings,
};
