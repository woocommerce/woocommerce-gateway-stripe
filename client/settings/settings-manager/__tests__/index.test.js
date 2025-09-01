import { render, screen, waitFor } from '@testing-library/react';
import { getQuery } from '@woocommerce/navigation';
import SettingsManager from '..';

jest.mock( '../../payment-settings' );

jest.mock( '../../payment-methods' );

jest.mock( '../../save-settings-section' );

jest.mock( 'wcstripe/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn().mockReturnValue( [ [], jest.fn() ] ),
	useSettings: jest.fn().mockReturnValue( {} ),
} ) );

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn().mockReturnValue( {
		data: null,
	} ),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
	updateQueryString: jest.fn(),
} ) );

jest.mock(
	'wcstripe/settings/notices/legacy-experience-transition',
	() => () => null
);

jest.mock(
	'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type',
	() => ( {
		getPromotionalBannerType: jest.fn().mockReturnValue( null ),
	} )
);

describe( 'SettingsManager', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = {
			accountStatus: {
				email: 'test@example.com',
				mode: 'test',
				paymentsEnabled: true,
				payoutsEnabled: true,
				accountLink: 'https://stripe.com/support',
			},
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render two tabs when mounted', async () => {
		render( <SettingsManager /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'tab', { name: /Payment Methods/i } )
			).toBeInTheDocument();
		} );

		await waitFor( () => {
			expect(
				screen.getByRole( 'tab', { name: /Settings/i } )
			).toBeInTheDocument();
		} );
	} );

	it( 'should render the Stripe payment method tab content by default', async () => {
		render( <SettingsManager /> );

		await waitFor( () => {
			expect(
				screen.queryByTestId( 'settings-tab' )
			).not.toBeInTheDocument();
		} );

		await waitFor( () => {
			expect( screen.queryByTestId( 'methods-tab' ) ).toBeInTheDocument();
		} );
	} );

	it( 'should render the general settings tab content when the URL matches', async () => {
		getQuery.mockReturnValue( { panel: 'settings' } );
		render( <SettingsManager /> );

		await waitFor( () => {
			expect(
				screen.queryByTestId( 'settings-tab' )
			).toBeInTheDocument();
		} );

		await waitFor( () => {
			expect(
				screen.queryByTestId( 'methods-tab' )
			).not.toBeInTheDocument();
		} );
	} );
} );
