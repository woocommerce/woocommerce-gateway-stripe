/**
 * External dependencies
 */
import React from 'react';
import { styled } from '@linaria/react';

/**
 * Internal dependencies
 */
import BaseIcon from './base-icon';

const IconSpacingMap = {
	small: '4px',
	medium: '7px',
};

const Wrapper = styled( BaseIcon )`
	background: white;
	border-color: #ddd;
	border-radius: 5px;
	overflow: hidden;
	padding: ${ ( { size } ) => IconSpacingMap[ size ] };
`;

const IconWithShell = ( { size = 'small', ...restProps } ) => (
	<Wrapper { ...restProps } size={ size } />
);

export default IconWithShell;
