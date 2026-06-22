import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import LinkSettingsSection from '../link-settings-section';
import { useEnabledPaymentMethodIds, useLinkLocations } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useLinkLocations: jest.fn(),
	useLinkButtonSize: jest.fn().mockReturnValue( [ 'default', jest.fn() ] ),
} ) );
jest.mock( '@woocommerce/blocks-checkout', () => {}, { virtual: true } );

describe( 'LinkSettingsSection locations', () => {
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

	it( 'should enable express checkout locations when Link is enabled', () => {
		render( <LinkSettingsSection /> );

		const checkoutCheckbox = screen.getByRole( 'checkbox', {
			name: /checkout/i,
		} );
		const productPageCheckbox = screen.getByRole( 'checkbox', {
			name: /product page/i,
		} );
		const cartCheckbox = screen.getByRole( 'checkbox', {
			name: /cart/i,
		} );

		expect( checkoutCheckbox ).not.toBeDisabled();
		expect( checkoutCheckbox ).toBeChecked();
		expect( productPageCheckbox ).not.toBeDisabled();
		expect( productPageCheckbox ).toBeChecked();
		expect( cartCheckbox ).not.toBeDisabled();
		expect( cartCheckbox ).toBeChecked();
	} );

	it( 'should trigger an action to save the checked locations when un-checking the location checkboxes', async () => {
		const updateLinkLocationsHandler = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'link' ],
			jest.fn(),
		] );
		useLinkLocations.mockReturnValue( [
			[ 'checkout', 'product', 'cart' ],
			updateLinkLocationsHandler,
		] );

		render( <LinkSettingsSection /> );

		// Uncheck each checkbox, and verify what kind of action should have been called
		await userEvent.click( screen.getByText( 'Product page' ) );

		expect( updateLinkLocationsHandler ).toHaveBeenLastCalledWith( [
			'checkout',
			'cart',
		] );

		await userEvent.click( screen.getByText( 'Checkout' ) );

		expect( updateLinkLocationsHandler ).toHaveBeenLastCalledWith( [
			'product',
			'cart',
		] );

		await userEvent.click( screen.getByText( 'Cart' ) );
		expect( updateLinkLocationsHandler ).toHaveBeenLastCalledWith( [
			'checkout',
			'product',
		] );
	} );

	it( 'should trigger an action to save the checked locations when checking the location checkboxes', async () => {
		const updateLinkLocationsHandler = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'link' ],
			jest.fn(),
		] );
		useLinkLocations.mockReturnValue( [ [], updateLinkLocationsHandler ] );

		render( <LinkSettingsSection /> );

		await userEvent.click( screen.getByText( 'Cart' ) );

		expect( updateLinkLocationsHandler ).toHaveBeenLastCalledWith( [
			'cart',
		] );

		await userEvent.click( screen.getByText( 'Product page' ) );

		expect( updateLinkLocationsHandler ).toHaveBeenLastCalledWith( [
			'product',
		] );

		await userEvent.click( screen.getByText( 'Checkout' ) );

		expect( updateLinkLocationsHandler ).toHaveBeenLastCalledWith( [
			'checkout',
		] );
	} );

	it( 'should not render the change payment method location when Subscriptions is inactive', () => {
		global.wc_stripe_link_settings_params.is_subscriptions_active = false;

		render( <LinkSettingsSection /> );

		expect(
			screen.queryByRole( 'checkbox', {
				name: /change payment method/i,
			} )
		).not.toBeInTheDocument();
	} );

	it( 'should render and toggle the change payment method location when Subscriptions is active', async () => {
		global.wc_stripe_link_settings_params.is_subscriptions_active = true;

		const updateLinkLocationsHandler = jest.fn();
		useLinkLocations.mockReturnValue( [
			[ 'checkout' ],
			updateLinkLocationsHandler,
		] );

		render( <LinkSettingsSection /> );

		const changePaymentMethodCheckbox = screen.getByRole( 'checkbox', {
			name: /change payment method/i,
		} );

		expect( changePaymentMethodCheckbox ).not.toBeDisabled();
		expect( changePaymentMethodCheckbox ).not.toBeChecked();

		await userEvent.click( changePaymentMethodCheckbox );

		expect( updateLinkLocationsHandler ).toHaveBeenLastCalledWith( [
			'checkout',
			'change_payment_method',
		] );
	} );
} );
