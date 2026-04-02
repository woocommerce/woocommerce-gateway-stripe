import React from 'react';
import { render, screen, waitFor, act } from '@testing-library/react';
import { getQuery } from '@woocommerce/navigation';
import SettingsManager from '..';
import PaymentMethodsPanel from '../../payment-methods';
import {
	BNPL_PROMOTION_BANNER,
	OC_PROMOTION_BANNER,
} from '../../payment-settings/constants';

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

const mockGetPromotionalBannerType = jest.fn().mockReturnValue( null );
jest.mock(
	'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type',
	() => ( {
		getPromotionalBannerType: ( ...args ) =>
			mockGetPromotionalBannerType( ...args ),
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
		mockGetPromotionalBannerType.mockReturnValue( null );
		getQuery.mockReturnValue( {} );
		PaymentMethodsPanel.mockImplementation(
			( { showPromotionalBanner } ) => (
				<div
					data-testid="payment-methods-panel"
					data-show-banner={ String( showPromotionalBanner ) }
				/>
			)
		);
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

	it( 'should show the banner when the promotional banner type is set and params indicate it should show', async () => {
		global.wc_stripe_settings_params.show_bnpl_promotional_banner = '1';
		mockGetPromotionalBannerType.mockReturnValue( BNPL_PROMOTION_BANNER );

		render( <SettingsManager /> );

		await waitFor( () => {
			expect(
				screen.queryByTestId( 'payment-methods-panel' )
			).toBeInTheDocument();
		} );
		await waitFor( () => {
			expect(
				screen.queryByTestId( 'payment-methods-panel' ).dataset
					.showBanner
			).toBe( 'true' );
		} );
	} );

	it( 'should reset showPromotionalBanner when the promotional banner type changes', async () => {
		// Start: BNPL banner shown
		global.wc_stripe_settings_params.show_bnpl_promotional_banner = '1';
		global.wc_stripe_settings_params.show_oc_promotional_banner = '';
		mockGetPromotionalBannerType.mockReturnValue( BNPL_PROMOTION_BANNER );

		const { rerender } = render( <SettingsManager /> );

		await waitFor( () => {
			expect(
				screen.queryByTestId( 'payment-methods-panel' ).dataset
					.showBanner
			).toBe( 'true' );
		} );

		// Change: switch to OC banner type where OC banner is not set to show
		mockGetPromotionalBannerType.mockReturnValue( OC_PROMOTION_BANNER );

		await act( async () => {
			rerender( <SettingsManager /> );
		} );

		await waitFor( () => {
			expect(
				screen.queryByTestId( 'payment-methods-panel' ).dataset
					.showBanner
			).toBe( 'false' );
		} );
	} );
} );
