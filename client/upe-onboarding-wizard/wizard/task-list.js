/**
 * External dependencies
 */
import React, { useLayoutEffect, useRef, useContext } from 'react';

/**
 * Internal dependencies
 */
import WizardContext from './wrapper/context';

const WizardTaskList = ( { children } ) => {
	const isFirstMount = useRef( true );
	const wrapperRef = useRef( null );
	const { activeTask } = useContext( WizardContext );

	useLayoutEffect( () => {
		// set the focus on the next active heading.
		// but need to set the focus only after the first mount, only when the active task changes.
		if ( isFirstMount.current === true ) {
			isFirstMount.current = false;
			return;
		}

		if ( ! wrapperRef.current ) {
			return;
		}

		const nextActiveTitle = wrapperRef.current.querySelector(
			'.wcpay-wizard-task.is-active .wcpay-wizard-task__headline'
		);
		if ( ! nextActiveTitle ) {
			return;
		}

		nextActiveTitle.focus();
	}, [ activeTask ] );

	return (
		<div ref={ wrapperRef }>
			<ul>{ children }</ul>
		</div>
	);
};

export default WizardTaskList;
