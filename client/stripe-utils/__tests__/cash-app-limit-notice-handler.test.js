import { screen } from '@testing-library/react';

describe( 'cash-app-limit-notice-handler', () => {
	it( 'does not render notice, wrapper not found and listener flag is false', () => {
		expect(
			screen.queryByTestId( 'cash-app-limit-notice' )
		).not.toBeInTheDocument();
	} );
	it( 'does not render notice, cart amount is below threshold, but try to wait for wrapper to exist (listener is true)', () => {} );
	it( 'render notice immediately (cart amount above threshold)', () => {} );
	it( 'render notice after wrapper exists (cart amount above threshold)', () => {} );
} );
