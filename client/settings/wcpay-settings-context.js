import { createContext } from 'react';

const WCPaySettingsContext = createContext( {
	accountFees: {},
	accountStatus: {},
	featureFlags: {},
	multiCurrency: {},
} );

export default WCPaySettingsContext;
