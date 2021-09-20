import { createContext } from 'react';

const UpeToggleContext = createContext( {
	isUpeEnabled: false,
	setIsUpeEnabled: () => null,
	status: 'resolved',
} );

export default UpeToggleContext;
