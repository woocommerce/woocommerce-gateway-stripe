import { render, screen } from '@testing-library/react';
import { ReconnectNotice } from 'wcstripe/settings/account-details/reconnect-notice';

describe( 'ReconnectNotice', () => {
	it( 'renders the reconnect notice', () => {
		render( <ReconnectNotice /> );

		expect(
			screen.getByText(
				/reconnect your stripe account using the new authentication flow to avoid disruptions on your store/i
			)
		).toBeInTheDocument();
		expect( screen.getByTestId( 'help' ) ).toBeInTheDocument();
	} );
} );
