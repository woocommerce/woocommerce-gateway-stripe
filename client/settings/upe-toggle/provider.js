import { useDispatch } from '@wordpress/data';
import { useCallback, useMemo, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import UpeToggleContext from './context';
import { NAMESPACE, STORE_NAME } from 'wcstripe/data/constants';
import { recordEvent } from 'wcstripe/tracking';

function trackUpeToggle( isEnabled ) {
	const eventName = isEnabled
		? 'wcstripe_legacy_experience_disabled'
		: 'wcstripe_legacy_experience_enabled';

	recordEvent( eventName, {
		source: 'settings-tab-checkbox',
	} );
}

const UpeToggleContextProvider = ( { children, defaultIsUpeEnabled } ) => {
	const [ isUpeEnabled, setIsUpeEnabled ] = useState(
		Boolean( defaultIsUpeEnabled )
	);
	const [ status, setStatus ] = useState( 'resolved' );
	const { invalidateResolutionForStoreSelector } = useDispatch( STORE_NAME );

	// We're now allowing to update UPE enabled status through the settings REST
	// API, for that reason we need to create a way to keep track of those changes
	// in here.
	const updateFlagLocally = useCallback(
		( value ) => {
			const sanitizedValue = Boolean( value );
			trackUpeToggle( sanitizedValue );
			setIsUpeEnabled( sanitizedValue );
		},
		[ setIsUpeEnabled ]
	);

	const updateFlag = useCallback(
		( value ) => {
			setStatus( 'pending' );

			const sanitizedValue = Boolean( value );

			return apiFetch( {
				path: `${ NAMESPACE }/upe_flag_toggle`,
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
		() => ( {
			isUpeEnabled,
			setIsUpeEnabled: updateFlag,
			setIsUpeEnabledLocally: updateFlagLocally,
			status,
		} ),
		[ isUpeEnabled, updateFlag, updateFlagLocally, status ]
	);

	return (
		<UpeToggleContext.Provider value={ contextValue }>
			{ children }
		</UpeToggleContext.Provider>
	);
};

export default UpeToggleContextProvider;
