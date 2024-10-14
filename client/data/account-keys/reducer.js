import ACTION_TYPES from './action-types';

const defaultState = {
	isTesting: false,
	isValid: null,
	isSaving: false,
	savingError: null,
	data: {},
	isConfiguringWebhooks: false,
};

export const accountKeysReducer = (
	state = defaultState,
	{ type, ...action }
) => {
	switch ( type ) {
		case ACTION_TYPES.SET_ACCOUNT_KEYS:
			return {
				...state,
				data: action.payload,
			};

		case ACTION_TYPES.SET_ACCOUNT_KEYS_VALUES:
			return {
				...state,
				savingError: null,
				data: {
					...state.data,
					...action.payload,
				},
			};

		case ACTION_TYPES.SET_IS_SAVING_ACCOUNT_KEYS:
			return {
				...state,
				isSaving: action.isSaving,
				savingError: action.error,
			};

		case ACTION_TYPES.SET_IS_TESTING_ACCOUNT_KEYS:
			return {
				...state,
				isTesting: action.isTesting,
			};

		case ACTION_TYPES.SET_IS_VALID_KEYS:
			return {
				...state,
				isValid: action.isValid,
			};

		case ACTION_TYPES.SET_IS_CONFIGURING_WEBHOOKS:
			return {
				...state,
				isConfiguringWebhooks: action.isProcessing,
			};
	}

	return state;
};

export default accountKeysReducer;
