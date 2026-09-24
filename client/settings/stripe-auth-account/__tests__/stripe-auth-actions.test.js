import React, { act } from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import StripeAuthActions from '../stripe-auth-actions';

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

global.wc_stripe_settings_params = {
	oauth_nonce: 'test-nonce',
};
global.ajaxurl = '/wp-admin/admin-ajax.php';

describe( 'StripeAuthActions', () => {
	it( 'should display the server error when reconnecting fails', async () => {
		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( {
				success: false,
				data: { message: 'The test account could not be connected.' },
			} ),
		};

		const { container } = render(
			<StripeAuthActions
				testMode={ true }
				displayWebhookConfigure={ false }
			/>
		);

		await act( async () => {
			await userEvent.click(
				screen.getByText( 'Create or connect a test account' )
			);
		} );

		await waitFor( () => {
			expect( container ).toHaveTextContent(
				'The test account could not be connected.'
			);
		} );
	} );

	it( 'should display the server error and re-enable the button when the request is rejected', async () => {
		global.jQuery = {
			ajax: jest.fn().mockRejectedValue( {
				responseJSON: {
					data: {
						message: 'The test account could not be connected.',
					},
				},
			} ),
		};

		const { container } = render(
			<StripeAuthActions
				testMode={ true }
				displayWebhookConfigure={ false }
			/>
		);

		const connectButton = screen.getByRole( 'button', {
			name: 'Create or connect a test account',
		} );

		await act( async () => {
			await userEvent.click( connectButton );
		} );

		await waitFor( () => {
			expect( container ).toHaveTextContent(
				'The test account could not be connected.'
			);
		} );
		expect( connectButton ).toBeEnabled();
	} );
} );
