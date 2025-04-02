import React from 'react';
import styled from '@emotion/styled';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon.svg';

const Wrapper = styled( IconWithShell )`
	background: #010101;
`;

const BlikIcon = ( props ) => <Wrapper { ...props } src={ icon } />;

export default BlikIcon;
