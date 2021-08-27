/**
 * External dependencies
 */
import { useCallback, useMemo, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import UpeToggleContext from './context';

const UpeToggleContextProvider = ( { children, defaultIsUpeEnabled } ) => {
	const [ isUpeEnabled, setIsUpeEnabled ] = useState(
		Boolean( defaultIsUpeEnabled )
	);
	const [ status, setStatus ] = useState( 'resolved' );

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
					setIsUpeEnabled( sanitizedValue );
					setStatus( 'resolved' );
				} )
				.catch( () => {
					setStatus( 'error' );
				} );
		},
		[ setStatus, setIsUpeEnabled ]
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
