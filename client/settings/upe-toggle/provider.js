/**
 * External dependencies
 */
import { useCallback, useMemo, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import UpeToggleContext from './context';
// eslint-disable-next-line @woocommerce/dependency-group,import/no-unresolved
import { STORE_NAME } from 'wcstripe/data/constants';
// eslint-disable-next-line @woocommerce/dependency-group,import/no-unresolved
import { recordEvent } from 'wcstripe/tracking';

function trackUpeToggle( isEnabled ) {
	const eventName = isEnabled
		? 'wcstripe_upe_enabled'
		: 'wcstripe_upe_disabled';

	recordEvent( eventName );
}

const UpeToggleContextProvider = ( { children, defaultIsUpeEnabled } ) => {
	const [ isUpeEnabled, setIsUpeEnabled ] = useState(
		Boolean( defaultIsUpeEnabled )
	);
	const [ status, setStatus ] = useState( 'resolved' );
	const { invalidateResolutionForStoreSelector } = useDispatch( STORE_NAME );

	const updateFlag = useCallback(
		( value ) => {
			setStatus( 'pending' );

			const sanitizedValue = Boolean( value );

			return apiFetch( {
				path: `/wc/v3/wc_stripe/upe_flag_toggle`,
				method: 'POST',
				data: { is_upe_enabled: sanitizedValue },
			} )
				.then( () => {
					trackUpeToggle( sanitizedValue );
					invalidateResolutionForStoreSelector( 'getSettings' );
					setIsUpeEnabled( sanitizedValue );
					setStatus( 'resolved' );
				} )
				.catch( () => {
					setStatus( 'error' );
				} );
		},
		[ setStatus, setIsUpeEnabled, invalidateResolutionForStoreSelector ]
	);

	const contextValue = useMemo(
		() => ( { isUpeEnabled, setIsUpeEnabled: updateFlag, status } ),
		[ isUpeEnabled, updateFlag, status ]
	);

	return (
		<UpeToggleContext.Provider value={ contextValue }>
			{ children }
		</UpeToggleContext.Provider>
	);
};

export default UpeToggleContextProvider;
