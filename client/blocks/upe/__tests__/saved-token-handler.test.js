import { render, screen } from '@testing-library/react';
import { SavedTokenHandler } from 'wcstripe/blocks/upe/saved-token-handler';
import { usePaymentCompleteHandler } from 'wcstripe/blocks/upe/hooks';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

jest.mock( 'wcstripe/blocks/upe/hooks' );

jest.mock( 'wcstripe/blocks/utils', () => ( {
	...jest.requireActual( 'wcstripe/blocks/utils' ),
	getBlocksConfiguration: jest.fn(),
} ) );

jest.mock(
	'@woocommerce/blocks-checkout',
	() => ( {
		StoreNotice: jest.fn( ( { children } ) => <div>{ children }</div> ),
		extensionCartUpdate: jest.fn().mockResolvedValue( {
			extensions: {
				'wc-stripe/checkout-session': {
					client_secret: 'test_secret',
					status: 'success',
				},
			},
		} ),
	} ),
	{ virtual: true }
);

jest.mock( 'wcstripe/blocks/load-stripe', () => ( {
	loadStripe: jest.fn().mockResolvedValue( {} ),
} ) );

jest.mock( '@stripe/react-stripe-js/checkout', () => ( {
	CheckoutElementsProvider: jest.fn( ( { children } ) => (
		<div data-testid="checkout-elements-provider">{ children }</div>
	) ),
	CurrencySelectorElement: jest.fn( () => <div /> ),
	useCheckout: jest.fn().mockReturnValue( { type: 'loading' } ),
} ) );

jest.mock( 'wcstripe/stripe-utils/upe-appearance', () => ( {
	initializeUPEAppearance: jest.fn().mockReturnValue( {} ),
} ) );

jest.mock( 'wcstripe/styles/upe', () => ( {
	getFontRulesFromPage: jest.fn().mockReturnValue( [] ),
} ) );

describe( 'SavedTokenHandler', () => {
	const api = { getStripe: () => ( {} ) };
	const stripe = {};
	const elements = {};
	const emitResponse = {};
	const onCheckoutSuccess = jest.fn();
	const onPaymentSetup = jest.fn();
	const onCheckoutFail = jest.fn();

	const renderHandler = ( extraProps = {} ) =>
		render(
			<SavedTokenHandler
				api={ api }
				stripe={ stripe }
				elements={ elements }
				eventRegistration={ {
					onCheckoutSuccess,
					onPaymentSetup,
					onCheckoutFail,
				} }
				emitResponse={ emitResponse }
				{ ...extraProps }
			/>
		);

	beforeEach( () => {
		jest.clearAllMocks();
		usePaymentCompleteHandler.mockImplementation( () => {} );
		getBlocksConfiguration.mockReturnValue( {
			isAdaptivePricingEnabled: false,
		} );
	} );

	it( 'renders without errors', () => {
		expect( () => renderHandler() ).not.toThrow();
	} );

	it( 'calls usePaymentCompleteHandler with onCheckoutSuccess', () => {
		renderHandler();

		expect( usePaymentCompleteHandler ).toHaveBeenCalledWith(
			api,
			stripe,
			elements,
			onCheckoutSuccess,
			emitResponse,
			false
		);
	} );

	it( 'does not save the payment when handling a saved token', () => {
		renderHandler();

		const [ , , , , , shouldSavePayment ] =
			usePaymentCompleteHandler.mock.calls[ 0 ];
		expect( shouldSavePayment ).toBe( false );
	} );

	it( 'stays on the store-currency flow when the token is not in the Adaptive Pricing map', () => {
		getBlocksConfiguration.mockReturnValue( {
			isAdaptivePricingEnabled: true,
			adaptivePricingSavedTokens: { 99: 'pm_other' },
		} );
		const apiWithInitCheckout = {
			getStripe: () => ( { initCheckoutElementsSdk: jest.fn() } ),
		};

		renderHandler( { api: apiWithInitCheckout, token: 12 } );

		expect( usePaymentCompleteHandler ).toHaveBeenCalled();
	} );

	it( 'mounts the Checkout Sessions provider for an Adaptive Pricing-eligible token', () => {
		getBlocksConfiguration.mockReturnValue( {
			isAdaptivePricingEnabled: true,
			adaptivePricingSavedTokens: { 12: 'pm_saved_card_12' },
		} );
		const apiWithInitCheckout = {
			getStripe: () => ( { initCheckoutElementsSdk: jest.fn() } ),
		};

		renderHandler( { api: apiWithInitCheckout, token: 12 } );

		expect( usePaymentCompleteHandler ).not.toHaveBeenCalled();
		expect(
			screen.getByTestId( 'checkout-elements-provider' )
		).toBeInTheDocument();
	} );

	it( 'falls back to the store-currency flow when Stripe.js lacks initCheckoutElementsSdk', () => {
		getBlocksConfiguration.mockReturnValue( {
			isAdaptivePricingEnabled: true,
			adaptivePricingSavedTokens: { 12: 'pm_saved_card_12' },
		} );

		renderHandler( { token: 12 } );

		expect( usePaymentCompleteHandler ).toHaveBeenCalled();
	} );
} );
