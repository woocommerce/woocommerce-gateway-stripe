import { recordEvent } from 'wcstripe/tracking';
import {
	detectStripeJsBuild,
	recordStripeJsCapability,
	resetStripeJsCapabilityGuard,
} from 'wcstripe/stripe-utils/stripe-js-capability';

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

// isStripe()-shaped base so the probe classifies from the checkout capabilities only.
const cloverStripe = () => ( {
	initCheckout: jest.fn(),
} );
const dahliaStripe = () => ( {
	initCheckout: jest.fn( () => {
		throw new Error( 'stripe.initCheckout() has been removed' );
	} ),
	initCheckoutElementsSdk: jest.fn(),
} );
const legacyStripe = () => ( {} );

describe( 'detectStripeJsBuild', () => {
	it.each( [
		[ 'clover', cloverStripe(), false ],
		[ 'dahlia', dahliaStripe(), true ],
		[ 'legacy', legacyStripe(), false ],
	] )( 'classifies %s builds', ( expectedBuild, stripe, expectsSdk ) => {
		const result = detectStripeJsBuild( stripe );
		expect( result.build ).toBe( expectedBuild );
		expect( result.hasInitCheckoutElementsSdk ).toBe( expectsSdk );
	} );

	it( 'never invokes the (throwing) initCheckout stub while probing', () => {
		const stripe = dahliaStripe();
		detectStripeJsBuild( stripe );
		expect( stripe.initCheckout ).not.toHaveBeenCalled();
	} );

	it( 'returns unknown when there is no Stripe instance', () => {
		expect( detectStripeJsBuild( null ).build ).toBe( 'unknown' );
	} );
} );

describe( 'recordStripeJsCapability', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		resetStripeJsCapabilityGuard();
	} );

	it( 'flags predicted_render_risk for a dahlia + Adaptive Pricing Blocks load', () => {
		recordStripeJsCapability( {
			stripe: dahliaStripe(),
			surface: 'blocks',
			serverData: {
				isAdaptivePricingEnabled: true,
				shouldShowOptimizedCheckout: true,
			},
		} );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_stripe_js_capability',
			expect.objectContaining( {
				checkout_surface: 'blocks',
				stripe_js_build: 'dahlia',
				adaptive_pricing_enabled: 'yes',
				predicted_render_risk: 'yes',
			} )
		);
	} );

	it( 'does not flag render risk for classic, since classic falls back on the throw', () => {
		recordStripeJsCapability( {
			stripe: dahliaStripe(),
			surface: 'classic',
			serverData: { isAdaptivePricingEnabled: true },
		} );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_stripe_js_capability',
			expect.objectContaining( {
				checkout_surface: 'classic',
				stripe_js_build: 'dahlia',
				predicted_render_risk: 'no',
			} )
		);
	} );

	it( 'does not flag render risk on the pinned clover build', () => {
		recordStripeJsCapability( {
			stripe: cloverStripe(),
			surface: 'blocks',
			serverData: { isAdaptivePricingEnabled: true },
		} );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_stripe_js_capability',
			expect.objectContaining( {
				stripe_js_build: 'clover',
				predicted_render_risk: 'no',
			} )
		);
	} );

	it( 'records at most one event per surface per page load', () => {
		const args = {
			stripe: cloverStripe(),
			surface: 'blocks',
			serverData: {},
		};
		recordStripeJsCapability( args );
		recordStripeJsCapability( args );

		expect( recordEvent ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'never throws, even if recordEvent blows up', () => {
		recordEvent.mockImplementationOnce( () => {
			throw new Error( 'tracks unavailable' );
		} );

		expect( () =>
			recordStripeJsCapability( {
				stripe: cloverStripe(),
				surface: 'classic',
				serverData: {},
			} )
		).not.toThrow();
	} );
} );
