import { render, screen } from '@testing-library/react';
import { CheckoutSessionsContainer } from 'wcstripe/blocks/checkout-sessions/checkout-sessions-container';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';

jest.mock( '@woocommerce/blocks-checkout', () => {}, { virtual: true } );

jest.mock( 'wcstripe/blocks/checkout-sessions/checkout-form', () => jest.fn() );

describe( 'CheckoutSessionsContainer', () => {
	it( 'should render the form', () => {
		CheckoutForm.mockReturnValue( <div>Checkout Form</div> );

		const api = {
			checkoutSessionsCreateSession: jest
				.fn()
				.mockResolvedValue( { client_secret: 'test_secret' } ),
		};
		render( <CheckoutSessionsContainer api={ api } /> );

		expect( CheckoutForm ).toHaveBeenCalledWith(
			expect.objectContaining( {
				api,
				onLoadError: expect.any( Function ),
			} ),
			{}
		);
		expect( screen.getByText( 'Checkout Form' ) ).toBeInTheDocument();
	} );

	it( 'should render an error notice if there is a payment processor load error', () => {
		const api = {
			checkoutSessionsCreateSession: jest
				.fn()
				.mockResolvedValue( { client_secret: 'test_secret' } ),
		};
		const { rerender } = render(
			<CheckoutSessionsContainer api={ api } />
		);

		// Simulate a load error by calling the onLoadError callback with an error message.
		const errorMessage = 'Failed to load payment processor';
		const onLoadError = CheckoutForm.mock.calls[ 0 ][ 0 ].onLoadError;
		onLoadError( { error: { message: errorMessage } } );

		rerender( <CheckoutSessionsContainer api={ api } /> );

		expect(
			screen.getByText( errorMessage, { exact: true } )
		).toBeInTheDocument();
	} );
} );
