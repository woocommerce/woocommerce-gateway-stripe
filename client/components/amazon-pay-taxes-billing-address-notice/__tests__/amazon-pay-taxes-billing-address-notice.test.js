import React from 'react';
import { render, screen } from '@testing-library/react';
import AmazonPayTaxesBillingAddressNotice from '..';
import { useAmazonPayEnabledSettings } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useAmazonPayEnabledSettings: jest.fn(),
} ) );

jest.mock( '@woocommerce/settings', () => ( {
	getAdminLink: ( path ) => `/wp-admin/${ path }`,
} ) );

describe( 'AmazonPayTaxesBillingAddressNotice', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should not render when Amazon Pay is disabled', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ false, jest.fn() ] );

		const { container } = render(
			<AmazonPayTaxesBillingAddressNotice
				areTaxesBasedOnBillingAddress={ true }
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not render when taxes are not based on billing address', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		const { container } = render(
			<AmazonPayTaxesBillingAddressNotice
				areTaxesBasedOnBillingAddress={ false }
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should render when Amazon Pay is enabled and taxes are based on billing address', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		const { container } = render(
			<AmazonPayTaxesBillingAddressNotice
				areTaxesBasedOnBillingAddress={ true }
			/>
		);

		// Check for the consolidated content for text to speech.
		expect(
			screen.getByText(
				'Amazon Pay does not support taxes based on the billing address. The checkout button will not be visible to shoppers with this setting in effect.',
				{ exact: true }
			)
		).toBeInTheDocument();

		// Check for the content within the <strong> element.
		expect(
			screen.getByText(
				'Amazon Pay does not support taxes based on the billing address.',
				{ exact: true }
			)
		).toBeInTheDocument();

		// Check for the content from the trailing sentence.
		expect(
			screen.getByText(
				'The checkout button will not be visible to shoppers with this setting in effect.',
				{ exact: true }
			)
		).toBeInTheDocument();

		expect(
			container.querySelector(
				'.wc-stripe-amazon-pay-taxes-billing-address-notice'
			)
		).toBeTruthy();

		const actionLink = screen.getByRole( 'link', {
			name: 'Update tax settings',
		} );
		expect( actionLink ).toBeInTheDocument();
		expect( actionLink ).toHaveAttribute(
			'href',
			'/wp-admin/admin.php?page=wc-settings&tab=tax'
		);
	} );
} );
