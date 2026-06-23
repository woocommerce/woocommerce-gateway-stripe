import React from 'react';
import { render } from '@testing-library/react';
import ConnectionErrorNotice from '../connection-error-notice';

describe( 'ConnectionErrorNotice', () => {
	it( 'renders the default connection error message when no message prop is provided', () => {
		const { container } = render( <ConnectionErrorNotice /> );

		expect( container ).toHaveTextContent(
			'An issue occurred generating a connection to Stripe'
		);
	} );

	it( 'renders a custom message when the message prop is provided', () => {
		const { container } = render(
			<ConnectionErrorNotice message="Something specific went wrong. {{Link}}docs{{/Link}}." />
		);

		expect( container ).toHaveTextContent(
			'Something specific went wrong.'
		);
		expect( container ).not.toHaveTextContent(
			'An issue occurred generating a connection to Stripe'
		);
	} );
} );
