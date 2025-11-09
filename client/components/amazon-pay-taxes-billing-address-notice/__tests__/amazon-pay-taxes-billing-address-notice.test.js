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

	const verifyNoticeText = ( text ) => {
		// Expect the notice text to appear twice:
		//  - Once in the div for spoken content, and
		//  - Once in the actual notice.
		const noticeTextElements = screen.getAllByText( text, { exact: true } );
		expect( noticeTextElements?.length ).toBe( 2 );
		expect( noticeTextElements[ 0 ] ).toBeInTheDocument();
		expect( noticeTextElements[ 1 ] ).toBeInTheDocument();
	};

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

		verifyNoticeText(
			'Amazon Pay does not support taxes based on the billing address. The checkout button will not be visible to shoppers with this setting in effect.'
		);

		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeTruthy();
	} );

	it( 'should show the action link when showUpdateSettingsLink is true', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		render(
			<AmazonPayTaxesBillingAddressNotice
				areTaxesBasedOnBillingAddress={ true }
				showUpdateSettingsLink={ true }
			/>
		);

		const actionLink = screen.getByRole( 'link', {
			name: 'Update tax settings',
		} );
		expect( actionLink ).toBeInTheDocument();
		expect( actionLink ).toHaveAttribute(
			'href',
			'/wp-admin/admin.php?page=wc-settings&tab=tax'
		);
	} );

	it( 'should not show the action link when showUpdateSettingsLink is false', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		render(
			<AmazonPayTaxesBillingAddressNotice
				areTaxesBasedOnBillingAddress={ true }
				showUpdateSettingsLink={ false }
			/>
		);

		expect(
			screen.queryByRole( 'link', {
				name: 'Update tax settings',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'should show the action link when showUpdateSettingsLink is not specified', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		render(
			<AmazonPayTaxesBillingAddressNotice
				areTaxesBasedOnBillingAddress={ true }
			/>
		);

		const actionLink = screen.getByRole( 'link', {
			name: 'Update tax settings',
		} );
		expect( actionLink ).toBeInTheDocument();
		expect( actionLink ).toHaveAttribute(
			'href',
			'/wp-admin/admin.php?page=wc-settings&tab=tax'
		);
	} );

	it( 'should respect the noticeStatus prop', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		const { container } = render(
			<AmazonPayTaxesBillingAddressNotice
				areTaxesBasedOnBillingAddress={ true }
				noticeStatus="warning"
			/>
		);

		verifyNoticeText(
			'Amazon Pay does not support taxes based on the billing address. The checkout button will not be visible to shoppers with this setting in effect.'
		);

		expect(
			container.querySelector( '.components-notice.is-warning' )
		).toBeTruthy();
	} );
} );
