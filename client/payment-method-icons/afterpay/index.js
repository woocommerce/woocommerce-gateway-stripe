import React from 'react';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon.svg';

const AfterpayIcon = ( props ) => (
	<IconWithShell { ...props } iconType="pad-vertical" src={ icon } />
);

export default AfterpayIcon;
