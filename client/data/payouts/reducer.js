import ACTION_TYPES from './action-types';

const defaultState = {
	balance: null,
	isLoadingBalance: false,
	balanceError: null,
	payouts: {
		data: [],
		hasMore: false,
	},
	isLoadingPayouts: false,
	payoutsError: null,
};

export const payoutsReducer = ( state = defaultState, { type, ...action } ) => {
	switch ( type ) {
		case ACTION_TYPES.SET_BALANCE:
			return {
				...state,
				balance: action.payload,
			};

		case ACTION_TYPES.SET_IS_LOADING_BALANCE:
			return {
				...state,
				isLoadingBalance: action.isLoading,
			};

		case ACTION_TYPES.SET_BALANCE_ERROR:
			return {
				...state,
				balanceError: action.error,
			};

		case ACTION_TYPES.SET_PAYOUTS:
			return {
				...state,
				payouts: {
					data: action.payload?.data ?? [],
					hasMore: action.payload?.has_more ?? false,
				},
			};

		case ACTION_TYPES.SET_IS_LOADING_PAYOUTS:
			return {
				...state,
				isLoadingPayouts: action.isLoading,
			};

		case ACTION_TYPES.SET_PAYOUTS_ERROR:
			return {
				...state,
				payoutsError: action.error,
			};
	}

	return state;
};

export default payoutsReducer;
