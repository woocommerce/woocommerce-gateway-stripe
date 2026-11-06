import React from 'react';
import { render, screen } from '@testing-library/react';
import ConnectionErrorNotice from '../connection-error-notice';

describe( 'ConnectionErrorNotice', () => {
	it( 'renders the default connection error message when no message prop is provided', () => {
		const { container } = render( <ConnectionErrorNotice /> );

		expect( container ).toHaveTextContent(
			'An issue occurred generating a connection to Stripe. Please try again.'
		);
		expect( container ).not.toHaveTextContent( 'valid SSL certificate' );
		expect(
			screen.getByRole( 'link', { name: /documentation/ } )
		).toBeInTheDocument();
	} );

	it( 'renders a custom message when the message prop is provided', () => {
		const { container } = render(
			<ConnectionErrorNotice message="Something {{Link}}specific{{/Link}} went wrong." />
		);

		expect( container ).toHaveTextContent(
			'Something {{Link}}specific{{/Link}} went wrong.'
		);
		expect( container ).not.toHaveTextContent(
			'An issue occurred generating a connection to Stripe'
		);
		expect(
			screen.getByRole( 'link', { name: /documentation/ } )
		).toBeInTheDocument();
	} );
} );
