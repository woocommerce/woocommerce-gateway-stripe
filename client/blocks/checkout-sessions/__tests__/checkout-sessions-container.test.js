import { useState } from 'react';
import { render, screen } from '@testing-library/react';
import { CheckoutProvider } from '@stripe/react-stripe-js/checkout';
import { CheckoutSessionsContainer } from 'wcstripe/blocks/checkout-sessions/checkout-sessions-container';
import { initializeUPEAppearance } from 'wcstripe/stripe-utils';
import { getFontRulesFromPage } from 'wcstripe/styles/upe';

jest.mock( 'react', () => ( {
	...jest.requireActual( 'react' ),
	useState: jest.fn(),
} ) );

jest.mock(
	'@woocommerce/blocks-checkout',
	() => ( {
		StoreNotice: jest.fn( ( { children } ) => <div>{ children }</div> ),
	} ),
	{ virtual: true }
);

jest.mock( '@stripe/react-stripe-js/checkout', () => ( {
	CheckoutProvider: jest.fn( ( { children, ...props } ) => (
		<div { ...props }>{ children }</div>
	) ),
} ) );

jest.mock( 'wcstripe/blocks/checkout-sessions/checkout-form' );

jest.mock( 'wcstripe/stripe-utils' );

jest.mock( 'wcstripe/styles/upe' );

jest.mock( 'wcstripe/blocks/load-stripe', () => ( {
	loadStripe: jest.fn( () => Promise.resolve( true ) ),
} ) );

describe( 'CheckoutSessionsContainer', () => {
	const api = {
		checkoutSessionsCreateSession: jest.fn().mockResolvedValue( {
			data: { client_secret: 'test_secret' },
		} ),
	};

	beforeEach( () => {
		initializeUPEAppearance.mockReturnValue( {} );
		getFontRulesFromPage.mockReturnValue( [] );
		useState.mockReturnValue( [ null, jest.fn() ] );
	} );

	it( 'should render the container', () => {
		render( <CheckoutSessionsContainer api={ api } /> );

		expect( CheckoutProvider ).toHaveBeenCalledWith(
			expect.objectContaining( {
				stripe: expect.any( Promise ),
				options: expect.any( Object ),
			} ),
			{}
		);
	} );

	it( 'should render an error notice if there is a payment processor load error', () => {
		useState.mockReturnValue( [
			{ error: { message: 'Failed to load payment processor' } },
			jest.fn(),
		] );
		render( <CheckoutSessionsContainer api={ api } /> );

		expect(
			screen.getByText( 'Failed to load payment processor' )
		).toBeInTheDocument();
	} );
} );
