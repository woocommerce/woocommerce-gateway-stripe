import ACTION_TYPES from './action-types';

const defaultState = {
	isRefreshing: false,
	data: {},
};

export const accountReducer = ( state = defaultState, { type, ...action } ) => {
	switch ( type ) {
		case ACTION_TYPES.SET_ACCOUNT:
			return {
				...state,
				data: action.payload,
			};

		case ACTION_TYPES.SET_IS_REFRESHING:
			return {
				...state,
				isRefreshing: action.isRefreshing,
			};
	}

	return state;
};

export default accountReducer;
