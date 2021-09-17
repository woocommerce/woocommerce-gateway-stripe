import React from 'react';
import styled from '@emotion/styled';
import { css } from '@emotion/react';
import BaseIcon from './base-icon';

const IconSpacingMap = {
	small: css`
		padding: 4px;
	`,
	medium: css`
		padding: 7px;
	`,
};

const Wrapper = styled( BaseIcon )`
	background: white;
	border-color: #ddd;
	border-radius: 5px;
	overflow: hidden;

	${ ( { size } ) => IconSpacingMap[ size ] || '' }
`;

const IconWithShell = ( { size = 'small', ...restProps } ) => (
	<Wrapper { ...restProps } size={ size } />
);

export default IconWithShell;
