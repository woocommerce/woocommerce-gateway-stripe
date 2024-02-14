import ACTION_TYPES from './action-types';

const defaultState = {
	isSaving: false,
	savingError: null,
	isSavingOrderedPaymentMethodIds: false,
	isCustomizingPaymentMethod: false,
	data: {},
};

export const receiveSettings = (
	state = defaultState,
	{ type, ...action }
) => {
	switch ( type ) {
		case ACTION_TYPES.SET_SETTINGS:
			return {
				...state,
				data: action.data,
			};

		case ACTION_TYPES.SET_SETTINGS_VALUES:
			return {
				...state,
				savingError: null,
				data: {
					...state.data,
					...action.payload,
				},
			};

		case ACTION_TYPES.SET_IS_SAVING_SETTINGS:
			return {
				...state,
				isSaving: action.isSaving,
				savingError: action.error,
			};
		case ACTION_TYPES.SET_IS_SAVING_ORDERED_PAYMENT_METHOD_IDS:
			return {
				...state,
				isSavingOrderedPaymentMethodIds:
					action.isSavingOrderedPaymentMethodIds,
			};
		case ACTION_TYPES.SET_IS_CUSTOMIZING_PAYMENT_METHOD:
			return {
				...state,
				isCustomizingPaymentMethod: action.isCustomizingPaymentMethod,
			};
	}

	return state;
};

export default receiveSettings;
