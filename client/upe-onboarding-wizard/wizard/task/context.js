/**
 * External dependencies
 */
import { createContext } from 'react';

const WizardTaskContext = createContext( {
	isActive: false,
	setActive: () => null,
	isCompleted: false,
	setCompleted: () => null,
	taskId: '',
} );

export default WizardTaskContext;
