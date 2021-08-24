/**
 * External dependencies
 */
import React, { useState, useMemo } from 'react';

import WizardContext from './context';

const Wizard = ( {
	children,
	defaultActiveTask = '',
	defaultCompletedTasks = {},
} ) => {
	const [ activeTask, setActiveTask ] = useState( defaultActiveTask );
	const [ completedTasks, setCompletedTasks ] = useState(
		defaultCompletedTasks
	);

	const contextValue = useMemo(
		() => ( {
			activeTask,
			setActiveTask,
			completedTasks,
			setCompletedTasks,
		} ),
		[ activeTask, setActiveTask, completedTasks ]
	);

	return (
		<WizardContext.Provider value={ contextValue }>
			{ children }
		</WizardContext.Provider>
	);
};

export default Wizard;
