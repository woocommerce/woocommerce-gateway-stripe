const availableMethods = [ 'card', 'giropay', 'sofort', 'sepa_debit' ];
let enabledMethods = [ 'card', 'sepa_debit' ];

//TODO, these should come from an endpoint/ data store.
const useEnabledPaymentMethodIds = () => {
	return [ enabledMethods, ( methods ) => {
		enabledMethods = methods;
	} ];
};
const useGetAvailablePaymentMethodIds = () => {
	return availableMethods;
};
const useSettings = () => {
	return {
		saveSettings: () => new Promise( ( resolve ) => {
			console.debug( 'Called saveSettings()' ); // TODO Remove this once an actual implementation is in place.
			setTimeout( () => {
				resolve( 'Success' );
			}, 500 );
		} ),
		isSaving: false,
	};
};

export {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useSettings,
};
