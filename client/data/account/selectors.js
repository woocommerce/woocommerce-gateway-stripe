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

export const getAccountCapabilities = ( state ) => {
	return getAccountData( state ).account?.capabilities ?? EMPTY_OBJ;
};

export const getAccountCapabilitiesByStatus = ( state, searchedStatus ) => {
	const capabilities = getAccountCapabilities( state );

	const filteredCapabilities = Object.entries( capabilities ).reduce(
		( capabilitiesByStatus, [ capability, status ] ) => {
			if ( status === searchedStatus ) {
				capabilitiesByStatus.push( capability );
			}
			return capabilitiesByStatus;
		},
		[]
	);

	return filteredCapabilities;
};

export const isRefreshingAccount = ( state ) => {
	return getAccountState( state ).isRefreshing || false;
};
