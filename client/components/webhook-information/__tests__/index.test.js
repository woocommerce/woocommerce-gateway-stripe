import React from 'react';
import { render } from '@testing-library/react';
import { WebhookInformation } from '..';
import { useAccount } from 'wcstripe/data/account';

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

describe( 'WebhookInformation', () => {
	it( 'Renders the WebhookInformation component', () => {
		useAccount.mockReturnValue( {
			data: { webhook_url: 'example.com' },
		} );

		const { container } = render( <WebhookInformation /> );

		expect( container.firstChild ).not.toBeNull();
		expect( container.firstChild ).toHaveTextContent(
			"Add the following webhook endpoint example.com to your Stripe account settings(opens in a new tab) (if there isn't one already). This will enable you to receive notifications on the charge statuses."
		);
	} );
} );
