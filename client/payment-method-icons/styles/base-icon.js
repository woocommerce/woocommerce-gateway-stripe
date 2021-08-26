/**
 * External dependencies
 */
import React from 'react';
import { styled } from '@linaria/react';

const IconSizesMap = {
	small: {
		height: '24px',
		width: '37px',
	},
	medium: {
		height: '40px',
		width: '65px',
	},
};

const Wrapper = styled.span`
	// also accounts for null size
	height: ${ ( { size } ) => IconSizesMap[ size ].height };
	width: ${ ( { size } ) => IconSizesMap[ size ].width };

	box-sizing: border-box;
	display: inline-flex;
	justify-content: center;

	// most icons need a border. Ensuring that the border is part of the icon's size allows for consistent spacing.
	// in this case, the icon's border is just transparent (but it's still part of the icon's size).
	border: 1px solid transparent;

	img {
		max-width: 100%;
	}
`;

const BaseIcon = ( { src, children, alt, size = 'small', ...restProps } ) => (
	<Wrapper { ...restProps } size={ size }>
		{ src ? <img src={ src } alt={ alt } /> : children }
	</Wrapper>
);

export default BaseIcon;
