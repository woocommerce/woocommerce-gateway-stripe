/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Wizard from '../wrapper';
import WizardTask from '../task';
import WizardTaskList from '../task-list';
import WizardTaskItem from '../task-item';
import WizardContext from '../wrapper/context';
import WizardTaskContext from '../task/context';

const GoToTaskButton = ( { goTo } ) => {
	const { setActiveTask } = useContext( WizardContext );

	return (
		<button onClick={ () => setActiveTask( goTo ) }>Go to { goTo }</button>
	);
};

const TaskContent = () => {
	const { isCompleted, isActive, taskId } = useContext( WizardTaskContext );

	return (
		<div>
			<p>
				{ taskId } is { isCompleted ? '' : 'not' } completed
			</p>
			<p>
				{ taskId } is { isActive ? '' : 'not' } active
			</p>
		</div>
	);
};

describe( 'Wizard', () => {
	it( 'should render with some the default completed tasks', () => {
		render(
			<Wizard
				defaultActiveTask="task-1"
				defaultCompletedTasks={ { 'task-0': true } }
			>
				<WizardTaskList>
					<WizardTask id="task-0">
						<WizardTaskItem title="Task 0" index={ 0 }>
							<TaskContent />
						</WizardTaskItem>
					</WizardTask>
					<WizardTask id="task-1">
						<WizardTaskItem title="Task 1" index={ 1 }>
							<TaskContent />
						</WizardTaskItem>
					</WizardTask>
				</WizardTaskList>
			</Wizard>
		);

		expect(
			screen.queryByText( 'task-0 is not active' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'task-0 is completed' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'task-1 is active' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'task-1 is not completed' )
		).toBeInTheDocument();
	} );

	it( 'should allow to navigate to another task', () => {
		render(
			<Wizard defaultActiveTask="task-1">
				<WizardTaskList>
					<WizardTask id="task-0">
						<WizardTaskItem title="Task 0" index={ 0 }>
							<TaskContent />
							<GoToTaskButton goTo="task-1" />
						</WizardTaskItem>
					</WizardTask>
					<WizardTask id="task-1">
						<WizardTaskItem title="Task 1" index={ 1 }>
							<TaskContent />
							<GoToTaskButton goTo="task-2" />
						</WizardTaskItem>
					</WizardTask>
					<WizardTask id="task-2">
						<WizardTaskItem title="Task 2" index={ 2 }>
							<TaskContent />
							<GoToTaskButton goTo="task-0" />
						</WizardTaskItem>
					</WizardTask>
				</WizardTaskList>
			</Wizard>
		);

		expect(
			screen.queryByText( 'task-0 is not active' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'task-1 is active' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'task-2 is not active' )
		).toBeInTheDocument();

		fireEvent.click( screen.getByText( 'Go to task-2' ) );

		expect(
			screen.queryByText( 'task-0 is not active' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'task-1 is not active' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'task-2 is active' ) ).toBeInTheDocument();

		fireEvent.click( screen.getByText( 'Go to task-0' ) );

		expect( screen.queryByText( 'task-0 is active' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'task-1 is not active' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'task-2 is not active' )
		).toBeInTheDocument();
	} );

	it( 'should set the new active element when navigating to a new task', () => {
		render(
			<Wizard defaultActiveTask="task-0">
				<WizardTaskList>
					<WizardTask id="task-0">
						<WizardTaskItem title="First Task" index={ 0 }>
							<TaskContent />
							<GoToTaskButton goTo="task-1" />
						</WizardTaskItem>
					</WizardTask>
					<WizardTask id="task-1">
						<WizardTaskItem title="Second Task" index={ 1 }>
							<TaskContent />
							<GoToTaskButton goTo="task-0" />
						</WizardTaskItem>
					</WizardTask>
				</WizardTaskList>
			</Wizard>
		);

		expect(
			screen.getByText( 'Second Task' ).parentElement
		).not.toHaveFocus();

		fireEvent.click( screen.getByText( 'Go to task-1' ) );

		expect( screen.getByText( 'Second Task' ).parentElement ).toHaveFocus();

		fireEvent.click( screen.getByText( 'Go to task-0' ) );

		expect( screen.getByText( 'First Task' ).parentElement ).toHaveFocus();
	} );
} );
