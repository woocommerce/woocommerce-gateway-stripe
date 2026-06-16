import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import LinkSettingsSection from '../link-settings-section';
import {
	useEnabledPaymentMethodIds,
	useLinkLocations,
	useLinkButtonSize,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useLinkLocations: jest.fn(),
	useLinkButtonSize: jest.fn().mockReturnValue( [ 'default', jest.fn() ] ),
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

	it( 'renders settings with defaults', () => {
		render( <LinkSettingsSection /> );

		// confirm settings headings.
		expect(
			screen.queryByRole( 'heading', { name: 'Appearance' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed.
		const [ sizeRadio ] = screen.queryAllByRole( 'radio' );
		expect( sizeRadio ).toBeInTheDocument();

		// confirm default values.
		expect( screen.getByLabelText( 'Default (48 px)' ) ).toBeChecked();
	} );

	it( 'triggers the hooks when the settings are being interacted with', async () => {
		const setButtonSizeMock = jest.fn();

		useLinkButtonSize.mockReturnValue( [ 'default', setButtonSizeMock ] );

		render( <LinkSettingsSection /> );

		expect( setButtonSizeMock ).not.toHaveBeenCalled();

		await userEvent.click( screen.getByLabelText( 'Large (56 px)' ) );

		expect( setButtonSizeMock ).toHaveBeenCalledWith( 'large' );
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
