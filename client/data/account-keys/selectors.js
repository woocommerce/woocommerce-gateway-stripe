const EMPTY_OBJ = {};

const getAccountKeysState = ( state ) => {
	if ( ! state ) {
		return EMPTY_OBJ;
	}

	return state.accountKeys || EMPTY_OBJ;
};

export const getAccountKeys = ( state ) => {
	return getAccountKeysState( state ).data || EMPTY_OBJ;
};

export const isSavingAccountKeys = ( state ) => {
	return getAccountKeysState( state ).isSaving || false;
};

export const getAccountKeysSavingError = ( state ) => {
	return getAccountKeysState( state ).savingError;
};
