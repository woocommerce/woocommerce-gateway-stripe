/** @format */

const EMPTY_OBJ = {};
const EMPTY_ARR = [];

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

export const getIsPaymentRequestEnabled = ( state ) => {
	return getSettings( state ).is_payment_request_enabled || false;
};

export const getSavingError = ( state ) => {
	return getSettingsState( state ).savingError;
};
