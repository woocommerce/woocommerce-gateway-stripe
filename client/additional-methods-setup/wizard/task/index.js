/**
 * External dependencies
 */
import React, { useMemo, useContext } from 'react';

import WizardContext from '../wrapper/context';
import WizardTaskContext from './context';

const WizardTask = ( { children, id = '' } ) => {
	const {
		activeTask,
		completedTasks,
		setActiveTask,
		setCompletedTasks,
	} = useContext( WizardContext );

	const contextValue = useMemo(
		() => ( {
			isActive: id === activeTask,
			setActive: () => setActiveTask( id ),
			setCompleted: ( payload = true, nextTask = '' ) => {
				setCompletedTasks( ( tasks ) => ( {
					...tasks,
					[ id ]: payload,
				} ) );

				if ( nextTask ) {
					setActiveTask( nextTask );
				}
			},
			taskId: id,
			isCompleted: Boolean( completedTasks[ id ] ),
		} ),
		[ setActiveTask, setCompletedTasks, activeTask, completedTasks, id ]
	);

	return (
		<WizardTaskContext.Provider value={ contextValue }>
			{ children }
		</WizardTaskContext.Provider>
	);
};

export default WizardTask;
