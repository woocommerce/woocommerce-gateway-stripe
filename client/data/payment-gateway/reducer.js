import ACTION_TYPES from './action-types';

const defaultState = {
	isSaving: false,
	savingError: null,
	data: {},
};

export const receivePaymentGateway = (
	state = defaultState,
	{ type, ...action }
) => {
	switch ( type ) {
		case ACTION_TYPES.SET_PAYMENT_GATEWAY:
			return {
				...state,
				data: action.data,
			};

		case ACTION_TYPES.SET_PAYMENT_GATEWAY_VALUES:
			return {
				...state,
				savingError: null,
				data: {
					...state.data,
					...action.payload,
				},
			};

		case ACTION_TYPES.SET_IS_SAVING_PAYMENT_GATEWAY:
			return {
				...state,
				isSaving: action.isSaving,
				savingError: action.error,
			};
	}

	return state;
};

export default receivePaymentGateway;
