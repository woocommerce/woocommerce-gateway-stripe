/**
 * External dependencies
 */
import { useContext, useState } from 'react';

/**
 * Internal dependencies
 */
import UpeToggleContext from '../upe-toggle/context';

export const useGetAvailablePaymentMethods = () => {
	// doing this check _only_ for testing purposes - this hook
	// should probably rely on a global state, once the data layer has been implemented.
	const { isUpeEnabled } = useContext( UpeToggleContext );
	if ( isUpeEnabled ) {
		return [ 'card', 'giropay', 'sofort', 'sepa_debit' ];
	}

	return [ 'card' ];
};

// placing 2 elements _just_ for testing purposes
export const useEnabledPaymentMethods = () => useState( [ 'card', 'giropay' ] );
