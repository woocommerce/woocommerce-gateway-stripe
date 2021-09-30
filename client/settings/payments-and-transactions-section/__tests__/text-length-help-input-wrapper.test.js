import React from 'react';
import { render, screen } from '@testing-library/react';
import TextLengthHelpInputWrapper from '../text-length-help-input-wrapper';

describe( 'TextLengthHelpInputWrapper', () => {
	it( 'renders the help text', () => {
		render(
			<TextLengthHelpInputWrapper textLength={ 20 } maxLength={ 22 }>
				<div>children</div>
			</TextLengthHelpInputWrapper>
		);

		expect( screen.queryByText( 'children' ) ).toBeInTheDocument();
		expect( screen.queryByText( '20 / 22' ) ).toBeInTheDocument();
	} );
} );
