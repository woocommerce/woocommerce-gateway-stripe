/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ExpressCheckoutsSettings from '../express-checkout-settings';
import ExpressCheckoutButtonPreview from '../express-checkout-button-preview';

jest.mock( '../express-checkout-button-preview' );
ExpressCheckoutButtonPreview.mockImplementation( () => '<></>' );

jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: jest.fn().mockReturnValue( null ),
} ) );
jest.mock( '@stripe/stripe-js', () => ( {
	loadStripe: jest.fn().mockReturnValue( null ),
} ) );

jest.mock( '../utils/utils', () => ( {
	getPaymentRequestData: jest.fn().mockReturnValue( {
		publishableKey: 'pk_test_123',
		accountId: '0001',
		locale: 'en',
	} ),
} ) );

describe( 'ExpressCheckoutsSettings', () => {
	test( 'renders title and description', () => {
		render( <ExpressCheckoutsSettings /> );

		const heading = screen.queryByRole( 'heading', {
			name: 'Express checkouts',
		} );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'renders settings', () => {
		render( <ExpressCheckoutsSettings /> );

		expect(
			screen.queryByRole( 'heading', { name: 'Call to action' } )
		).toBeInTheDocument();
	} );

	test( 'renders payment request settings and confirm its h2 copy', () => {
		render( <ExpressCheckoutsSettings /> );

		const heading = screen.queryByRole( 'heading', {
			name: 'Express checkouts',
		} );
		expect( heading ).toBeInTheDocument();
	} );
} );
