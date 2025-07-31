import { useDispatch } from '@wordpress/data';
import { useCallback, useMemo, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { NAMESPACE, STORE_NAME } from 'wcstripe/data/constants';
import OCToggleContext from 'wcstripe/settings/oc-toggle/context';
import { recordEvent } from 'wcstripe/tracking';

/**
 * Tracks the toggle state of the Optimized Checkout feature.
 *
 * @param {boolean} isEnabled The current state of the Optimized Checkout feature.
 */
function trackOCToggle( isEnabled ) {
	const eventName = isEnabled
		? 'wcstripe_optimized_checkout_disabled'
		: 'wcstripe_optimized_checkout_enabled';
	recordEvent( eventName, {
		source: 'settings-tab-checkbox',
	} );
}

/**
 * @param {Object} props The component props.
 * @param {JSX.Element} props.children The child components that will consume the context.
 * @param {boolean} props.defaultIsOCEnabled Initial state of the Optimized Checkout feature.
 * @return {JSX.Element} The context provider component that wraps the children.
 */
const OCToggleContextProvider = ( { children, defaultIsOCEnabled } ) => {
	const [ isOCEnabled, setIsOCEnabled ] = useState(
		Boolean( defaultIsOCEnabled )
	);
	const [ status, setStatus ] = useState( 'resolved' );
	const { invalidateResolutionForStoreSelector } = useDispatch( STORE_NAME );

	const updateSettingLocally = useCallback(
		( value ) => {
			const sanitizedValue = Boolean( value );
			trackOCToggle( sanitizedValue );
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
					trackOCToggle( sanitizedValue );
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
