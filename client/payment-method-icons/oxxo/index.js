import React from 'react';
import styled from '@emotion/styled';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon.svg';

const Wrapper = styled( IconWithShell )`
	padding-top: 4px;
	padding-bottom: 4px;
`;

const OxxoIcon = ( props ) => <Wrapper { ...props } src={ icon } />;

export default OxxoIcon;
