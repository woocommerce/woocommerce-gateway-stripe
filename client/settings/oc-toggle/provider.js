import { useDispatch } from '@wordpress/data';
import { useCallback, useMemo, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { NAMESPACE, STORE_NAME } from 'wcstripe/data/constants';
import OCToggleContext from 'wcstripe/settings/oc-toggle/context';

const OCToggleContextProvider = ( { children, defaultIsOCEnabled } ) => {
	const [ isOCEnabled, setIsOCEnabled ] = useState(
		Boolean( defaultIsOCEnabled )
	);
	const [ status, setStatus ] = useState( 'resolved' );
	const { invalidateResolutionForStoreSelector } = useDispatch( STORE_NAME );

	const updateSettingLocally = useCallback(
		( value ) => {
			const sanitizedValue = Boolean( value );
			setIsOCEnabled( sanitizedValue );
		},
		[ setIsOCEnabled ]
	);

	const updateSetting = useCallback(
		( value ) => {
			setStatus( 'pending' );

			const sanitizedValue = Boolean( value );

			return apiFetch( {
				path: `${ NAMESPACE }/oc_setting_toggle`,
				method: 'POST',
				data: { is_oc_enabled: sanitizedValue },
			} )
				.then( () => {
					invalidateResolutionForStoreSelector( 'getSettings' );
					setIsOCEnabled( sanitizedValue );
					setStatus( 'resolved' );
				} )
				.catch( () => {
					setStatus( 'error' );
				} );
		},
		[ setStatus, setIsOCEnabled, invalidateResolutionForStoreSelector ]
	);

	const contextValue = useMemo(
		() => ( {
			isOCEnabled,
			setIsOCEnabled: updateSetting,
			setIsOCEnabledLocally: updateSettingLocally,
			status,
		} ),
		[ isOCEnabled, updateSetting, updateSettingLocally, status ]
	);

	return (
		<OCToggleContext.Provider value={ contextValue }>
			{ children }
		</OCToggleContext.Provider>
	);
};

export default OCToggleContextProvider;
