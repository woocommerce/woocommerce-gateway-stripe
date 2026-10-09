import { getSetting } from '@woocommerce/settings';
import { initializeCheckoutIcons } from 'wcstripe/blocks/upe/checkout-icons';

jest.mock( '@woocommerce/settings', () => ( {
	getSetting: jest.fn(),
} ) );

jest.mock(
	'wcstripe/blocks/upe/upe-deferred-intent-creation/payment-elements',
	() => ( { getDeferredIntentCreationUPEFields: jest.fn() } )
);
jest.mock( 'wcstripe/blocks/upe/saved-token-handler', () => ( {
	SavedTokenHandler: jest.fn(),
} ) );
jest.mock( 'wcstripe/blocks/upe/checkout-icons', () => ( {
	initializeCheckoutIcons: jest.fn( () => ( {} ) ),
} ) );
jest.mock( 'wcstripe/payment-method-icons', () => ( {} ) );
jest.mock( 'wcstripe/api', () => jest.fn() );
jest.mock( 'wcstripe/stripe-utils/copy-test-number', () => ( {} ) );

const importUpeElement = () => {
	jest.isolateModules( () => {
		require( 'wcstripe/blocks/upe/upe-element' );
	} );
};

describe( 'upe-element import', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'does not throw when the Stripe configuration is missing', () => {
		getSetting.mockReturnValue( null );

		expect( importUpeElement ).not.toThrow();
		expect( initializeCheckoutIcons ).toHaveBeenCalledWith( false );
	} );

	it( 'uses the admin flag from the Stripe configuration', () => {
		getSetting.mockImplementation( ( name, fallback ) =>
			name === 'stripe_data' ? { isAdmin: true } : fallback
		);

		importUpeElement();

		expect( initializeCheckoutIcons ).toHaveBeenCalledWith( true );
	} );
} );
