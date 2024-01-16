import React from 'react';
import styled from '@emotion/styled';

const SectionStatusWrapper = styled.span`
	display: flex;
	height: 24px;
	padding: 0px 4px;
	margin-left: 6px;
	justify-content: center;
	align-items: center;
	border-radius: 2px;

	color: ${ ( props ) => ( props.enabled ? '#005c12' : '#614200' ) };
	background-color: ${ ( props ) =>
		props.enabled ? '#edfaef' : '#fcf9e8' };
`;

const SectionStatus = ( { isEnabled, children } ) => {
	return (
		<SectionStatusWrapper enabled={ isEnabled }>
			{ children }
		</SectionStatusWrapper>
	);
};

export default SectionStatus;
