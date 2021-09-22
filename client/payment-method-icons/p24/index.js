import React from 'react';
import styled from '@emotion/styled';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon.svg';

const Wrapper = styled( IconWithShell )`
	padding-top: 0;
	padding-bottom: 0;
`;

const P24Icon = ( props ) => <Wrapper { ...props } src={ icon } />;

export default P24Icon;
