const EMPTY_OBJ = {};

const getAccountState = ( state ) => {
	if ( ! state ) {
		return EMPTY_OBJ;
	}

	return state.account || EMPTY_OBJ;
};

export const getAccountData = ( state ) => {
	return getAccountState( state ).data || EMPTY_OBJ;
};

export const isRefreshingAccount = ( state ) => {
	return getAccountState( state ).isRefreshing || false;
};
