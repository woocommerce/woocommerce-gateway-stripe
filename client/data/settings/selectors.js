const EMPTY_OBJ = {};

const getSettingsState = ( state ) => {
	if ( ! state ) {
		return EMPTY_OBJ;
	}

	return state.settings || EMPTY_OBJ;
};

export const getSettings = ( state ) => {
	return getSettingsState( state ).data || EMPTY_OBJ;
};

export const isSavingSettings = ( state ) => {
	return getSettingsState( state ).isSaving || false;
};

export const getSavingError = ( state ) => {
	return getSettingsState( state ).savingError;
};

export const getOrderedPaymentMethodIds = ( state ) => {
	return (
		getSettingsState( state ).data?.ordered_payment_method_ids || EMPTY_OBJ
	);
};

export const isSavingOrderedPaymentMethodIds = ( state ) => {
	return getSettingsState( state ).isSavingOrderedPaymentMethodIds || false;
};
