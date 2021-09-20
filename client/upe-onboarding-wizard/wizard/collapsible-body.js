import React, { useContext } from 'react';
import classNames from 'classnames';
import WizardTaskContext from './task/context';
import './style.scss';

const CollapsibleBody = ( { className, ...restProps } ) => {
	const { isActive } = useContext( WizardTaskContext );

	return (
		<div
			className={ classNames( 'task-collapsible-body', className, {
				'is-active': isActive,
			} ) }
			{ ...restProps }
		/>
	);
};

export default CollapsibleBody;
