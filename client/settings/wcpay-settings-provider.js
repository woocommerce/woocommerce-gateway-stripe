import { useMemo, useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import WCPaySettingsContext from './wcpay-settings-context';

const WCPaySettingsContextProvider = ( { children } ) => {
	const [ multiCurrency, setMultiCurrency ] = useState( {} );
	const [ status, setStatus ] = useState( 'resolved' );

	useEffect( () => {
		setStatus( 'pending' );
		const fetchMultiCurrency = async () => {
			try {
				const multiCurrencyData = await apiFetch( {
					path: `/wc/v3/payments/multi-currency/currencies`,
				} );
				setMultiCurrency( multiCurrencyData );
				setStatus( 'resolved' );
			} catch ( _ ) {
				setStatus( 'error' );
			}
		};
		fetchMultiCurrency();
	}, [] );

	const contextValue = useMemo( () => ( { multiCurrency, status } ), [
		multiCurrency,
		status,
	] );

	return (
		<WCPaySettingsContext.Provider value={ contextValue }>
			{ children }
		</WCPaySettingsContext.Provider>
	);
};

export default WCPaySettingsContextProvider;
