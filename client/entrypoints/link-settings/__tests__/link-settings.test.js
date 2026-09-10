import { render, screen } from '@testing-library/react';
import LinkSettingsSection from '../link-settings-section';
import { useEnabledPaymentMethodIds, useLinkLocations } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useLinkLocations: jest.fn(),
	useExpressCheckoutButtonSize: jest
		.fn()
		.mockReturnValue( [ 'default', jest.fn() ] ),
} ) );
jest.mock( '@woocommerce/blocks-checkout', () => {}, { virtual: true } );

describe( 'LinkSettingsSection', () => {
	const globalValues = global.wc_stripe_link_settings_params;
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'link' ],
			jest.fn(),
		] );

		useLinkLocations.mockReturnValue( [
			[ 'checkout', 'product', 'cart' ],
			jest.fn(),
		] );

		global.wc_stripe_link_settings_params = {
			...globalValues,
			key: 'pk_test_123',
			locale: 'en',
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_link_settings_params = globalValues;
	} );

	it( 'renders the locations section and preview, without a per-method size control', () => {
		render( <LinkSettingsSection /> );

		// Location checkboxes render.
		expect(
			screen.getByRole( 'checkbox', { name: /checkout/i } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Preview' ) ).toBeInTheDocument();

		// The size control now lives on the Express Checkout settings page.
		expect(
			screen.queryByRole( 'heading', { name: 'Appearance' } )
		).not.toBeInTheDocument();
		expect( screen.queryAllByRole( 'radio' ) ).toHaveLength( 0 );
	} );

	it( 'hides the appearance override notice by default', () => {
		const { container } = render( <LinkSettingsSection /> );

		expect(
			container.querySelector( '.components-notice' )
		).not.toBeInTheDocument();
	} );

	it( 'shows the appearance override notice when styles are overridden', () => {
		global.wc_stripe_link_settings_params = {
			...global.wc_stripe_link_settings_params,
			is_button_style_overridden: true,
		};

		const { container } = render( <LinkSettingsSection /> );

		expect(
			container.querySelector( '.components-notice' )
		).toHaveTextContent( /Some appearance settings may be overridden/i );
	} );
} );
