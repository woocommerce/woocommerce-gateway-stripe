const EMPTY_OBJ = {};

const getPaymentGatewayState = ( state ) => {
	if ( ! state ) {
		return EMPTY_OBJ;
	}

	return state.paymentGateway || EMPTY_OBJ;
};

export const getPaymentGateway = ( state ) => {
	return getPaymentGatewayState( state ).data || EMPTY_OBJ;
};

export const isSavingPaymentGateway = ( state ) => {
	return getPaymentGatewayState( state ).isSaving || false;
};

export const getPaymentGatewaySavingError = ( state ) => {
	return getPaymentGatewayState( state ).savingError;
};
