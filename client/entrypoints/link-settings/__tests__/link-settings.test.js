import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import LinkSettingsSection from '../link-settings-section';
import {
	useEnabledPaymentMethodIds,
	useLinkLocations,
	useExpressCheckoutButtonSize,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useLinkLocations: jest.fn(),
	useExpressCheckoutButtonSize: jest.fn(),
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

		useExpressCheckoutButtonSize.mockReturnValue( [
			'default',
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

	it( 'renders the locations section, the shared size control, and the preview', () => {
		render( <LinkSettingsSection /> );

		// Location checkboxes render.
		expect(
			screen.getByRole( 'checkbox', { name: /checkout/i } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Preview' ) ).toBeInTheDocument();

		// The shared button size can be edited from this page too.
		expect(
			screen.getByRole( 'heading', { name: 'Appearance' } )
		).toBeInTheDocument();
		expect( screen.getByText( /Small/ ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				/This size applies to all express checkout buttons/i
			)
		).toBeInTheDocument();
	} );

	it( 'updates the shared button size when a size is selected', async () => {
		const setSize = jest.fn();
		useExpressCheckoutButtonSize.mockReturnValue( [ 'default', setSize ] );

		render( <LinkSettingsSection /> );

		// Not called until the click, so the click is what triggers the update.
		expect( setSize ).not.toHaveBeenCalled();

		await userEvent.click( screen.getByLabelText( /Large/ ) );

		expect( setSize ).toHaveBeenCalledWith( 'large' );
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
