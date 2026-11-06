import React from 'react';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon.svg';

const P24Icon = ( props ) => (
	<IconWithShell { ...props } src={ icon } iconType="flush-vertical" />
);

export default P24Icon;
