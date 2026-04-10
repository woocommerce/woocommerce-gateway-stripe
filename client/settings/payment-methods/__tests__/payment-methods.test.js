import { render, screen } from '@testing-library/react';
import PaymentMethods from '..';

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

describe( 'PaymentMethods', () => {
	const globalValues = global.wc_stripe_settings_params;

	beforeEach( () => {
		global.wc_stripe_settings_params = {
			...globalValues,
			taxes_based_on_billing: false,
		};
	} );

	it( 'shows the Amazon Pay Taxes Billing Address Notice when taxes are based on billing address', () => {
		global.wc_stripe_settings_params = {
			...global.wc_stripe_settings_params,
			taxes_based_on_billing: true,
		};

		render( <PaymentMethods /> );

		expect(
			screen.getByTestId( 'amazon-pay-taxes-notice' )
		).toBeInTheDocument();
	} );

	it( 'does not show the Amazon Pay Taxes Billing Address Notice when taxes are not based on billing address', () => {
		global.wc_stripe_settings_params = {
			...global.wc_stripe_settings_params,
			taxes_based_on_billing: false,
		};

		render( <PaymentMethods /> );

		expect(
			screen.queryByTestId( 'amazon-pay-taxes-notice' )
		).not.toBeInTheDocument();
	} );
} );
