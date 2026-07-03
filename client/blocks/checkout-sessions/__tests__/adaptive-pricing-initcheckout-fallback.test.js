/**
 * Regression coverage for the OCS "Payment Element rendered" drop after 10.8.3.
 *
 * 10.8.3 (#5618) removed the Stripe.js capability guard that used to route
 * Adaptive Pricing loads to standard Elements when Stripe.js no longer supported
 * initCheckout(). On "dahlia+" Stripe.js, initCheckout() is a throwing stub.
 *
 * These tests exercise the REAL @stripe/react-stripe-js CheckoutProvider (the
 * component the Blocks Adaptive Pricing path mounts) to demonstrate the asymmetry
 * that makes the render drop Blocks-only:
 *
 *   - Blocks falls back to standard Elements only when checkoutState.type === 'error'
 *     (see checkout-form.js). react-stripe-js sets that state ONLY from the async
 *     loadActions() path — a SYNCHRONOUS initCheckout() throw never produces it, so
 *     the Blocks fallback cannot fire and the Payment Element fails to render.
 *   - The classic path wraps initCheckout() in try/catch and renders anyway; see
 *     ../../../classic/upe/__tests__/payment-processing.test.js.
 */
import React from 'react';
import { render, waitFor } from '@testing-library/react';
import {
	CheckoutProvider,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';

// A minimal object that passes react-stripe-js's isStripe() validation
// (elements/createToken/createPaymentMethod/confirmCardPayment must be functions).
const baseStripe = () => ( {
	elements: jest.fn(),
	createToken: jest.fn(),
	createPaymentMethod: jest.fn(),
	confirmCardPayment: jest.fn(),
} );

// dahlia+: initCheckout() is a throwing stub; the replacement method exists.
const dahliaStripe = () => ( {
	...baseStripe(),
	initCheckout: jest.fn( () => {
		throw new Error( 'stripe.initCheckout() has been removed' );
	} ),
	initCheckoutElementsSdk: jest.fn(),
} );

// A working-but-async-failing build: initCheckout() returns an SDK whose
// loadActions() rejects. This is the ONLY shape that reaches checkoutState 'error'.
const asyncFailingStripe = () => ( {
	...baseStripe(),
	initCheckout: jest.fn( () => ( {
		loadActions: jest.fn( () =>
			Promise.reject( new Error( 'loadActions failed' ) )
		),
	} ) ),
} );

class ErrorBoundary extends React.Component {
	constructor( props ) {
		super( props );
		this.state = { caught: false };
	}
	static getDerivedStateFromError() {
		return { caught: true };
	}
	componentDidCatch( error ) {
		this.props.onCatch?.( error );
	}
	render() {
		return this.state.caught ? null : this.props.children;
	}
}

// Records every checkoutState.type the provider exposes across the render lifecycle,
// mirroring how checkout-form.js reads useCheckout() to decide whether to fall back.
const StateProbe = ( { seen } ) => {
	const checkoutState = useCheckout();
	seen.add( checkoutState?.type );
	return null;
};

const renderProvider = ( stripe, seen, onCatch ) =>
	render(
		<ErrorBoundary onCatch={ onCatch }>
			<CheckoutProvider
				stripe={ stripe }
				options={ { clientSecret: 'cs_test_123' } }
			>
				<StateProbe seen={ seen } />
			</CheckoutProvider>
		</ErrorBoundary>
	);

describe( 'Blocks Adaptive Pricing initCheckout fallback', () => {
	let consoleErrorSpy;

	beforeEach( () => {
		// React logs caught render/effect errors; silence the expected noise.
		consoleErrorSpy = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
	} );

	afterEach( () => {
		consoleErrorSpy.mockRestore();
	} );

	it( 'never reaches checkoutState "error" when initCheckout throws synchronously (dahlia+), so the Blocks fallback cannot fire', async () => {
		const seen = new Set();
		const onCatch = jest.fn();

		renderProvider( dahliaStripe(), seen, onCatch );

		// The synchronous throw surfaces as an uncaught error (error boundary),
		// NOT as checkoutState.type === 'error'. checkout-form.js keys its
		// standard-Elements fallback on 'error', so it can never run here.
		await waitFor( () => expect( onCatch ).toHaveBeenCalled() );
		expect( seen.has( 'error' ) ).toBe( false );
	} );

	it( 'does reach checkoutState "error" when the async loadActions path fails, which is the only case the Blocks fallback handles', async () => {
		const seen = new Set();
		const onCatch = jest.fn();

		renderProvider( asyncFailingStripe(), seen, onCatch );

		await waitFor( () => expect( seen.has( 'error' ) ).toBe( true ) );
		// No synchronous throw in this path.
		expect( onCatch ).not.toHaveBeenCalled();
	} );
} );
