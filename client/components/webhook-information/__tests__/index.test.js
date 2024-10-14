import React from 'react';
import { render } from '@testing-library/react';
import { WebhookInformation } from '..';

describe( 'WebhookInformation', () => {
	it( 'Renders the WebhookInformation component', () => {
		const { container } = render( <WebhookInformation /> );

		expect( container.firstChild ).not.toBeNull();
		expect( container.firstChild ).toHaveTextContent(
			'Click the Configure connection button to configure a webhook(opens in a new tab). This will complete your Stripe account connection process.'
		);
	} );
} );
