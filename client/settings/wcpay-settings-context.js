import { createContext } from 'react';

const WCPaySettingsContext = createContext( {
	accountFees: {},
	accountStatus: {},
	featureFlags: {},
} );

export default WCPaySettingsContext;
