import { getSetting } from '@woocommerce/settings';
import {
	registerExpressPaymentMethod,
	registerPaymentMethod,
} from '@woocommerce/blocks-registry';
import {
	addOrderAttributionInputsIfNotExists,
	populateOrderAttributionInputs,
} from 'wcstripe/blocks/utils';
import { updateTokenLabelsWhenLoaded } from 'wcstripe/blocks/upe/token-label-updater.js';

jest.mock( '@woocommerce/settings', () => ( {
	getSetting: jest.fn(),
} ) );

jest.mock(
	'@woocommerce/blocks-registry',
	() => ( {
		registerPaymentMethod: jest.fn(),
		registerExpressPaymentMethod: jest.fn(),
	} ),
	{ virtual: true }
);

jest.mock( 'wcstripe/api', () => jest.fn() );

jest.mock( 'wcstripe/blocks/upe/upe-element', () => ( {
	upeElement: jest.fn( ( method ) => ( { name: method } ) ),
} ) );

jest.mock( 'wcstripe/blocks/express-checkout', () => ( {
	expressCheckoutElementAmazonPay: jest.fn(),
	expressCheckoutElementApplePay: jest.fn( () => ( { name: 'apple' } ) ),
	expressCheckoutElementGooglePay: jest.fn(),
	expressCheckoutElementStripeLink: jest.fn(),
} ) );

jest.mock( 'wcstripe/blocks/upe/token-label-updater.js', () => ( {
	updateTokenLabelsWhenLoaded: jest.fn(),
} ) );

jest.mock( 'wcstripe/blocks/utils', () => ( {
	...jest.requireActual( 'wcstripe/blocks/utils' ),
	addOrderAttributionInputsIfNotExists: jest.fn(),
	populateOrderAttributionInputs: jest.fn(),
} ) );

jest.mock( 'wcstripe/blocks/upe/styles.scss', () => ( {} ), {
	virtual: true,
} );
jest.mock( 'wcstripe/blocks/express-checkout/styles.scss', () => ( {} ), {
	virtual: true,
} );

const loadEntry = () => {
	jest.isolateModules( () => {
		require( 'wcstripe/blocks/upe/index.js' );
	} );
};

describe( 'Stripe blocks entry', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'does not throw or register anything when the Stripe configuration is missing', () => {
		getSetting.mockReturnValue( null );

		expect( loadEntry ).not.toThrow();
		expect( registerPaymentMethod ).not.toHaveBeenCalled();
		expect( registerExpressPaymentMethod ).not.toHaveBeenCalled();
		expect( updateTokenLabelsWhenLoaded ).not.toHaveBeenCalled();
		expect( addOrderAttributionInputsIfNotExists ).not.toHaveBeenCalled();
		expect( populateOrderAttributionInputs ).not.toHaveBeenCalled();
	} );

	it( 'registers the payment methods when the Stripe configuration is available', () => {
		getSetting.mockImplementation( ( name, fallback ) =>
			name === 'stripe_data'
				? {
						paymentMethodsConfig: { card: {}, link: {} },
						isApplePayEnabled: true,
				  }
				: fallback
		);

		loadEntry();

		expect( registerPaymentMethod ).toHaveBeenCalledTimes( 1 );
		expect( registerPaymentMethod ).toHaveBeenCalledWith( {
			name: 'card',
		} );
		expect( registerExpressPaymentMethod ).toHaveBeenCalledWith( {
			name: 'apple',
		} );
		expect( updateTokenLabelsWhenLoaded ).toHaveBeenCalled();
		expect( addOrderAttributionInputsIfNotExists ).toHaveBeenCalled();
		expect( populateOrderAttributionInputs ).toHaveBeenCalled();
	} );
} );
