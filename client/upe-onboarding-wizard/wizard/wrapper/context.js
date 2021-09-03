/**
 * External dependencies
 */
import { createContext } from 'react';

const WizardContext = createContext( {
	activeTask: '',
	setActiveTask: () => null,
	completedTasks: {},
	setCompletedTasks: () => null,
} );

export default WizardContext;
