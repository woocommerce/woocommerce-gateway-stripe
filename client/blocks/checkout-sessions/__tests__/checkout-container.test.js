import { useState } from 'react';
import { render } from '@testing-library/react';
import { CheckoutProvider } from '@stripe/react-stripe-js/checkout';
import { CheckoutContainer } from 'wcstripe/blocks/checkout-sessions/checkout-container';
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
	const setShouldLoadStripeElements = jest.fn();

	beforeEach( () => {
		initializeUPEAppearance.mockReturnValue( {} );
		getFontRulesFromPage.mockReturnValue( [] );
		useState.mockReturnValue( [ null, jest.fn() ] );
	} );

	it( 'should render the container', () => {
		render(
			<CheckoutContainer
				api={ api }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
			/>
		);

		expect( CheckoutProvider ).toHaveBeenCalledWith(
			expect.objectContaining( {
				stripe: expect.any( Promise ),
				options: expect.any( Object ),
			} ),
			{}
		);
	} );
} );
