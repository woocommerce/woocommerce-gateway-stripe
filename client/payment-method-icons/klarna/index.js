import React from 'react';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon.svg';

const KlarnaIcon = ( props ) => (
	<IconWithShell { ...props } iconType="flush-outlined" src={ icon } />
);

export default KlarnaIcon;
