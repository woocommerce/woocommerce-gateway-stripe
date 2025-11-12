import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AmazonPayEnableSection from '../amazon-pay-enable-section';
import { useAmazonPayEnabledSettings } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useAmazonPayEnabledSettings: jest.fn(),
} ) );

jest.mock(
	'wcstripe/components/amazon-pay-taxes-billing-address-notice',
	() => {
		return jest.fn( ( { areTaxesBasedOnBillingAddress } ) => {
			if ( ! areTaxesBasedOnBillingAddress ) {
				return null;
			}
			return (
				<div data-testid="amazon-pay-taxes-notice">
					Taxes based on billing address notice
				</div>
			);
		} );
	}
);

describe( 'AmazonPayEnableSection', () => {
	const globalValues = global.wc_stripe_amazon_pay_settings_params;
	let updateIsAmazonPayEnabledMock;

	beforeEach( () => {
		updateIsAmazonPayEnabledMock = jest.fn();
		useAmazonPayEnabledSettings.mockReturnValue( [
			false,
			updateIsAmazonPayEnabledMock,
		] );

		global.wc_stripe_amazon_pay_settings_params = {
			...globalValues,
			taxes_based_on_billing: false,
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_amazon_pay_settings_params = globalValues;
	} );

	it( 'renders the checkbox control with correct label and help text', () => {
		render( <AmazonPayEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Amazon Pay/i,
		} );
		expect( checkbox ).toBeInTheDocument();
		expect( checkbox ).not.toBeChecked();

		// Check help text is present
		expect(
			screen.getByText(
				'When enabled, customers who have configured Amazon Pay enabled devices will be able to pay with their respective choice of Wallet.'
			)
		).toBeInTheDocument();
	} );

	it( 'renders checkbox as checked when Amazon Pay is enabled', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [
			true,
			updateIsAmazonPayEnabledMock,
		] );

		render( <AmazonPayEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Amazon Pay/i,
		} );
		expect( checkbox ).toBeChecked();
	} );

	it( 'renders checkbox as unchecked when Amazon Pay is disabled', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [
			false,
			updateIsAmazonPayEnabledMock,
		] );

		render( <AmazonPayEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Amazon Pay/i,
		} );
		expect( checkbox ).not.toBeChecked();
	} );

	it( 'calls updateIsAmazonPayEnabled when checkbox is clicked', async () => {
		render( <AmazonPayEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Amazon Pay/i,
		} );

		expect( updateIsAmazonPayEnabledMock ).not.toHaveBeenCalled();

		await userEvent.click( checkbox );

		expect( updateIsAmazonPayEnabledMock ).toHaveBeenCalledWith( true );
	} );

	it( 'calls updateIsAmazonPayEnabled with false when unchecking enabled checkbox', async () => {
		useAmazonPayEnabledSettings.mockReturnValue( [
			true,
			updateIsAmazonPayEnabledMock,
		] );

		render( <AmazonPayEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Amazon Pay/i,
		} );

		await userEvent.click( checkbox );

		expect( updateIsAmazonPayEnabledMock ).toHaveBeenCalledWith( false );
	} );

	it( 'renders AmazonPayTaxesBillingAddressNotice with areTaxesBasedOnBillingAddress=true when taxes are based on billing address', () => {
		global.wc_stripe_amazon_pay_settings_params = {
			...global.wc_stripe_amazon_pay_settings_params,
			taxes_based_on_billing: true,
		};

		render( <AmazonPayEnableSection /> );

		expect(
			screen.getByTestId( 'amazon-pay-taxes-notice' )
		).toBeInTheDocument();
	} );

	it( 'does not render AmazonPayTaxesBillingAddressNotice when taxes are not based on billing address', () => {
		global.wc_stripe_amazon_pay_settings_params = {
			...global.wc_stripe_amazon_pay_settings_params,
			taxes_based_on_billing: false,
		};

		render( <AmazonPayEnableSection /> );

		expect(
			screen.queryByTestId( 'amazon-pay-taxes-notice' )
		).not.toBeInTheDocument();
	} );

	it( 'does not render AmazonPayTaxesBillingAddressNotice when taxes_based_on_billing is undefined', () => {
		global.wc_stripe_amazon_pay_settings_params = {
			...global.wc_stripe_amazon_pay_settings_params,
			taxes_based_on_billing: undefined,
		};

		render( <AmazonPayEnableSection /> );

		expect(
			screen.queryByTestId( 'amazon-pay-taxes-notice' )
		).not.toBeInTheDocument();
	} );

	it( 'does not render AmazonPayTaxesBillingAddressNotice when wc_stripe_amazon_pay_settings_params is undefined', () => {
		global.wc_stripe_amazon_pay_settings_params = undefined;

		render( <AmazonPayEnableSection /> );

		expect(
			screen.queryByTestId( 'amazon-pay-taxes-notice' )
		).not.toBeInTheDocument();
	} );

	it( 'renders the component with express-checkout-settings class', () => {
		const { container } = render( <AmazonPayEnableSection /> );

		const card = container.querySelector( '.express-checkout-settings' );
		expect( card ).toBeInTheDocument();
	} );
} );
