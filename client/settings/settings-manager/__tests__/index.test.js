import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { getQuery } from '@woocommerce/navigation';
import SettingsManager from '..';
import SaveSettingsSection from '../../save-settings-section';
import AgenticCommerceSection from '../../agentic-commerce';

jest.mock( '../../payment-settings' );

jest.mock( '../../payment-methods' );

jest.mock( '../../agentic-commerce', () => {
	const React = require( 'react' );
	const Mock = jest.fn();
	const ForwardedMock = React.forwardRef( ( props, ref ) =>
		Mock( props, ref )
	);
	ForwardedMock.mockImplementation = Mock.mockImplementation.bind( Mock );
	return ForwardedMock;
} );

jest.mock( '../../save-settings-section', () => jest.fn() );

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
	'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type',
	() => ( {
		getPromotionalBannerType: jest.fn().mockReturnValue( null ),
	} )
);

const BASE_PARAMS = {
	accountStatus: {
		email: 'test@example.com',
		mode: 'test',
		paymentsEnabled: true,
		payoutsEnabled: true,
		accountLink: 'https://stripe.com/support',
	},
	is_agentic_commerce_enabled: false,
};

describe( 'SettingsManager', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { ...BASE_PARAMS };
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render two tabs', async () => {
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

	it( 'should render an Agentic Commerce tab when the feature is enabled', async () => {
		global.wc_stripe_settings_params = {
			...BASE_PARAMS,
			is_agentic_commerce_enabled: true,
		};

		render( <SettingsManager /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'tab', { name: /Agentic Commerce/i } )
			).toBeInTheDocument();
		} );
	} );

	it( 'should not render an Agentic Commerce tab when the feature is disabled', async () => {
		render( <SettingsManager /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'tab', { name: /Settings/i } )
			).toBeInTheDocument();
		} );

		expect(
			screen.queryByRole( 'tab', { name: /Agentic/i } )
		).not.toBeInTheDocument();
	} );

	it( 'should open the Agentic Commerce tab when the URL panel matches', async () => {
		global.wc_stripe_settings_params = {
			...BASE_PARAMS,
			is_agentic_commerce_enabled: true,
		};
		getQuery.mockReturnValue( { panel: 'agentic-commerce' } );

		render( <SettingsManager /> );

		await waitFor( () => {
			expect(
				screen.queryByTestId( 'agentic-commerce-tab' )
			).toBeInTheDocument();
		} );

		expect( screen.queryByTestId( 'methods-tab' ) ).not.toBeInTheDocument();
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

	it( 'should fall back to methods tab for unknown panel values', async () => {
		getQuery.mockReturnValue( { panel: 'agentic-commerce' } );

		render( <SettingsManager /> );

		await waitFor( () => {
			expect( screen.queryByTestId( 'methods-tab' ) ).toBeInTheDocument();
		} );
	} );

	it( 'renders SaveSettingsSection on the agentic tab only when onSettingsLoad(true) is fired', async () => {
		global.wc_stripe_settings_params = {
			...BASE_PARAMS,
			is_agentic_commerce_enabled: true,
		};
		getQuery.mockReturnValue( { panel: 'agentic-commerce' } );

		SaveSettingsSection.mockImplementation( () => (
			<button>Save changes</button>
		) );

		AgenticCommerceSection.mockImplementation( ( { onSettingsLoad } ) => {
			return (
				<div>
					<button onClick={ () => onSettingsLoad( true ) }>
						Simulate Load Success
					</button>
				</div>
			);
		} );

		render( <SettingsManager /> );

		// Initially not loaded, so Save changes button should not be in the document.
		expect( screen.queryByText( 'Save changes' ) ).not.toBeInTheDocument();

		// Trigger load success.
		fireEvent.click( screen.getByText( 'Simulate Load Success' ) );

		// Now Save changes should be rendered.
		expect( screen.getByText( 'Save changes' ) ).toBeInTheDocument();
	} );
} );
