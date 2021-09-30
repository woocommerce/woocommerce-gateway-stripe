import React from 'react';
import styled from '@emotion/styled';
import { Icon, info } from '@wordpress/icons';

const AlertIcon = styled( Icon )`
	fill: #d94f4f;
	margin-right: 4px;
`;

const Wrapper = styled.span`
	display: inline-flex;
	align-items: center;
`;

const AlertTitle = ( { title } ) => (
	<Wrapper>
		<AlertIcon icon={ info } />
		{ title }
	</Wrapper>
);

export default AlertTitle;
