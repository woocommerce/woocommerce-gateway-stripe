import React, { useContext } from 'react';
import classNames from 'classnames';
import { Icon, check } from '@wordpress/icons';
import WizardTaskContext from './task/context';
import './style.scss';

const WizardTaskItem = ( { children, title, index, className } ) => {
	const { isCompleted, isActive } = useContext( WizardTaskContext );

	return (
		<li
			className={ classNames( 'wcstripe-wizard-task', className, {
				'is-completed': isCompleted,
				'is-active': isActive,
			} ) }
		>
			<div className="wcstripe-wizard-task__top-border" />
			<div
				className="wcstripe-wizard-task__headline"
				// tabindex with value `-1` is necessary to programmatically set the focus
				// on an element that is not interactive.
				tabIndex="-1"
			>
				<div className="wcstripe-wizard-task__icon-wrapper">
					<div className="wcstripe-wizard-task__icon-text">
						{ index }
					</div>
					<Icon
						icon={ check }
						className="wcstripe-wizard-task__icon-checkmark"
					/>
				</div>
				<span className="wcstripe-wizard-task__title">{ title }</span>
			</div>
			<div className="wcstripe-wizard-task__body">{ children }</div>
		</li>
	);
};

export default WizardTaskItem;
