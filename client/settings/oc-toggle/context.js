import { createContext } from 'react';

const OCToggleContext = createContext( {
	isOCEnabled: false,
	setIsOCEnabled: () => null,
	status: 'resolved',
} );

export default OCToggleContext;
