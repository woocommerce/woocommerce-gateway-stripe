import { render } from '@testing-library/react';
import {
	expressCheckoutElementAmazonPay,
	expressCheckoutElementApplePay,
	expressCheckoutElementGooglePay,
	expressCheckoutElementStripeLink,
} from 'wcstripe/blocks/express-checkout';
import { PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT } from 'wcstripe/blocks/express-checkout/constants';
import {
	AmazonPayPreview,
	ApplePayPreview,
	GooglePayPreview,
	StripeLinkPreview,
} from 'wcstripe/blocks/express-checkout/express-button-previews';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { checkPaymentMethodIsAvailable } from 'wcstripe/express-checkout/utils/check-payment-method-availability';
import { ExpressCheckoutContainer } from 'wcstripe/blocks/express-checkout/express-checkout-container';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_LINK,
} from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/blocks/load-stripe' );
jest.mock( 'wcstripe/blocks/utils' );
jest.mock(
	'wcstripe/express-checkout/utils/check-payment-method-availability'
);

// Mock the express button previews
jest.mock( 'wcstripe/blocks/express-checkout/express-button-previews', () => ( {
	AmazonPayPreview: jest.fn( () => <div /> ),
	ApplePayPreview: jest.fn( () => <div /> ),
	GooglePayPreview: jest.fn( () => <div /> ),
	StripeLinkPreview: jest.fn( () => <div /> ),
} ) );

// Mock the ExpressCheckoutContainer component
jest.mock( '../express-checkout/express-checkout-container', () => ( {
	ExpressCheckoutContainer: jest.fn( () => <div /> ),
} ) );

describe( 'expressCheckoutElement', () => {
	const api = {};

	beforeEach( () => {
		loadStripe.mockReturnValue( Promise.resolve( {} ) );
		getBlocksConfiguration.mockReturnValue( {
			shouldShowExpressCheckoutButton: true,
			supports: [],
			isAdmin: false,
		} );
		checkPaymentMethodIsAvailable.mockImplementation(
			( _method, _api, _cart, resolve ) => {
				resolve( true );
			}
		);
	} );

	const testCases = [
		{
			fn: expressCheckoutElementAmazonPay,
			paymentMethod: EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
			editorElement: AmazonPayPreview,
			title: 'WooCommerce Stripe - Amazon Pay',
		},
		{
			fn: expressCheckoutElementApplePay,
			paymentMethod: EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
			editorElement: ApplePayPreview,
			title: 'WooCommerce Stripe - Apple Pay',
		},
		{
			fn: expressCheckoutElementGooglePay,
			paymentMethod: EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
			editorElement: GooglePayPreview,
			title: 'WooCommerce Stripe - Google Pay',
		},
		{
			fn: expressCheckoutElementStripeLink,
			paymentMethod: EXPRESS_PAYMENT_METHOD_SETTING_LINK,
			editorElement: StripeLinkPreview,
			title: 'WooCommerce Stripe - Link by Stripe',
		},
	];

	testCases.forEach( ( { fn, paymentMethod, editorElement, title } ) => {
		it( `should return the correct config for ${ paymentMethod }`, async () => {
			const config = fn( api );

			expect( config.name ).toBe(
				`${ PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT }_${ paymentMethod }`
			);
			expect( config.title ).toBe( title );

			render( config.content );
			expect( ExpressCheckoutContainer ).toHaveBeenCalledWith(
				expect.objectContaining( {
					expressPaymentMethod: paymentMethod,
				} ),
				{}
			);

			render( config.edit );
			expect( editorElement ).toHaveBeenCalledTimes( 1 );

			expect( config.paymentMethodId ).toBe(
				PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT
			);
			expect( config.gatewayId ).toBe( 'stripe' );
		} );
	} );
} );
