/**
 * External dependencies
 */
import React from 'react';
import styled from '@emotion/styled';

const Wrapper = styled.div`
	max-width: 500px;
	position: relative;

	.components-base-control__field .components-text-control__input {
		// to make room for the help text, so that the input's text and the help text don't overlap
		padding-right: 55px;
	}
`;

const HelpText = styled.span`
	position: absolute;
	right: 10px;
	top: 38px;
	font-size: 12px;
	color: #757575;

	@media ( min-width: 783px ) {
		top: 32px;
	}
`;

const TextLengthHelpInputWrapper = ( {
	children,
	textLength = 0,
	maxLength,
} ) => (
	<Wrapper>
		{ children }
		<HelpText aria-hidden="true">
			{ `${ textLength } / ${ maxLength }` }
		</HelpText>
	</Wrapper>
);

export default TextLengthHelpInputWrapper;
