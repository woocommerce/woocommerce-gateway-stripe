import { expressCheckoutElementGooglePay } from '..';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import {
	checkPaymentMethodIsAvailable,
	preloadPaymentMethodAvailability,
} from 'wcstripe/express-checkout/utils/check-payment-method-availability';

jest.mock( '../express-checkout-container', () => ( {
	ExpressCheckoutContainer: () => null,
} ) );
jest.mock( '../express-button-previews', () => ( {
	AmazonPayPreview: () => null,
	ApplePayPreview: () => null,
	GooglePayPreview: () => null,
	StripeLinkPreview: () => null,
} ) );
jest.mock( 'wcstripe/blocks/load-stripe', () => ( {
	loadStripe: () => Promise.resolve( null ),
} ) );
jest.mock( 'wcstripe/blocks/utils', () => ( {
	getBlocksConfiguration: jest.fn(),
} ) );
jest.mock( 'wcstripe/express-checkout/utils', () => ( {
	getExpressCheckoutData: () => false,
} ) );
jest.mock(
	'wcstripe/express-checkout/utils/check-payment-method-availability',
	() => ( {
		checkPaymentMethodIsAvailable: jest.fn(),
		preloadPaymentMethodAvailability: jest.fn(),
	} )
);

describe( 'Blocks wallet registration', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		getBlocksConfiguration.mockReturnValue( {
			shouldShowExpressCheckoutButton: true,
		} );
		global.wc_stripe_express_checkout_params = {};
	} );

	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
	} );

	it( 'starts cached discovery while registering a wallet', () => {
		const api = {};
		expressCheckoutElementGooglePay( api );
		expect( preloadPaymentMethodAvailability ).toHaveBeenCalledWith( api );
	} );

	it( 'keeps a zero-total live cart ineligible', () => {
		const method = expressCheckoutElementGooglePay( {} );
		expect(
			method.canMakePayment( {
				cart: { cartTotals: { total_price: '0' } },
			} )
		).toBe( false );
		expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
	} );

	it( 'uses the live cart when checking availability', () => {
		const api = {};
		const cart = { cartTotals: { total_price: '1000' } };
		const result = Promise.resolve( false );
		checkPaymentMethodIsAvailable.mockReturnValue( result );
		const method = expressCheckoutElementGooglePay( api );
		expect( method.canMakePayment( { cart } ) ).toBe( result );
		expect( checkPaymentMethodIsAvailable ).toHaveBeenCalledWith(
			'googlePay',
			api,
			cart
		);
	} );

	it( 'does not prewarm without express checkout configuration', () => {
		delete global.wc_stripe_express_checkout_params;
		const method = expressCheckoutElementGooglePay( {} );
		expect( preloadPaymentMethodAvailability ).not.toHaveBeenCalled();
		expect(
			method.canMakePayment( {
				cart: { cartTotals: { total_price: '1000' } },
			} )
		).toBe( false );
	} );

	it( 'keeps a server-disabled wallet section ineligible', () => {
		getBlocksConfiguration.mockReturnValue( {
			shouldShowExpressCheckoutButton: false,
		} );
		const method = expressCheckoutElementGooglePay( {} );
		expect(
			method.canMakePayment( {
				cart: { cartTotals: { total_price: '1000' } },
			} )
		).toBe( false );
		expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
	} );
} );
