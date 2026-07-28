const EMPTY_OBJ = {};
const EMPTY_ARR = [];

const getPayoutsState = ( state ) => {
	if ( ! state ) {
		return EMPTY_OBJ;
	}

	return state.payouts || EMPTY_OBJ;
};

export const getBalance = ( state ) => {
	return getPayoutsState( state ).balance || null;
};

export const isLoadingBalance = ( state ) => {
	return getPayoutsState( state ).isLoadingBalance || false;
};

export const getBalanceError = ( state ) => {
	return getPayoutsState( state ).balanceError || null;
};

export const getPayouts = ( state ) => {
	return getPayoutsState( state ).payouts?.data || EMPTY_ARR;
};

export const getPayoutsHasMore = ( state ) => {
	return getPayoutsState( state ).payouts?.hasMore || false;
};

export const isLoadingPayouts = ( state ) => {
	return getPayoutsState( state ).isLoadingPayouts || false;
};

export const getPayoutsError = ( state ) => {
	return getPayoutsState( state ).payoutsError || null;
};
