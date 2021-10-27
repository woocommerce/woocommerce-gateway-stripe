import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback } from 'react';
import { getQuery } from '@woocommerce/navigation';
import { STORE_NAME } from '../constants';

const { section } = getQuery();

const makeReadOnlyPaymentGatewayHook = (
	fieldName,
	fieldDefaultValue = false
) => () =>
	useSelect(
		( select ) => {
			const { getPaymentGateway } = select( STORE_NAME );

			return getPaymentGateway()[ fieldName ] || fieldDefaultValue;
		},
		[ fieldName, fieldDefaultValue ]
	);

const makePaymentGatewayHook = (
	fieldName,
	fieldDefaultValue = false
) => () => {
	const { updatePaymentGatewayValues } = useDispatch( STORE_NAME );

	const field = makeReadOnlyPaymentGatewayHook(
		fieldName,
		fieldDefaultValue
	)();

	const handler = useCallback(
		( value ) =>
			updatePaymentGatewayValues( {
				[ fieldName ]: value,
			} ),
		[ updatePaymentGatewayValues ]
	);

	return [ field, handler ];
};

export const usePaymentGateway = () => {
	const { savePaymentGateway } = useDispatch( STORE_NAME );

	const paymentGateway = useSelect( ( select ) => {
		const { getPaymentGateway } = select( STORE_NAME );

		return getPaymentGateway();
	}, [] );

	const isLoading = useSelect( ( select ) => {
		const { hasFinishedResolution, isResolving } = select( STORE_NAME );

		return (
			isResolving( 'getPaymentGateway' ) ||
			! hasFinishedResolution( 'getPaymentGateway' )
		);
	}, [] );

	const isSaving = useSelect( ( select ) => {
		const { isSavingPaymentGateway } = select( STORE_NAME );

		return isSavingPaymentGateway();
	}, [] );

	return { paymentGateway, isLoading, isSaving, savePaymentGateway };
};

export const useEnabledPaymentGateway = makePaymentGatewayHook( `is_${ section }_enabled` );

export const usePaymentGatewayName = makePaymentGatewayHook( `${ section }_name`, '' );;

export const usePaymentGatewayDescription = makePaymentGatewayHook( `${ section }_description`, '' )
