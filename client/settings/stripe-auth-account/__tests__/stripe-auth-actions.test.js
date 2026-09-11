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

		render(
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
			expect(
				screen.getAllByText(
					'The test account could not be connected.'
				)
			).not.toHaveLength( 0 );
		} );
	} );
} );
