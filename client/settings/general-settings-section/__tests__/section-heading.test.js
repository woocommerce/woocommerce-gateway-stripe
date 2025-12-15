import { render, screen } from '@testing-library/react';
import { expect } from '@playwright/test';
import SectionHeading from 'wcstripe/settings/general-settings-section/section-heading';
import { useIsOCEnabled, useGetOrderedPaymentMethodIds } from 'wcstripe/data';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';
import { useAccount } from 'wcstripe/data/account';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useIsOCEnabled: jest.fn(),
	useGetOrderedPaymentMethodIds: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

describe( 'SectionHeading', () => {
	beforeEach( () => {
		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [ PAYMENT_METHOD_CARD ],
			isSaving: false,
			saveOrderedPaymentMethodIds: jest.fn(),
		} );
		useAccount.mockReturnValue( {
			refreshAccount: jest.fn(),
		} );
	} );

	it( 'default display', () => {
		const { getByText, getByLabelText } = render(
			<SectionHeading isChangingDisplayOrder={ false } />
		);

		expect( getByText( 'Payment methods' ) ).toBeInTheDocument();
		expect( getByText( 'Change display order' ) ).toBeInTheDocument();
		expect( getByLabelText( 'Payment methods menu' ) ).toBeInTheDocument();
	} );

	it( 'is changing display order', () => {
		const { getByText } = render(
			<SectionHeading isChangingDisplayOrder={ true } />
		);

		expect( getByText( 'Payment methods' ) ).toBeInTheDocument();
		expect( getByText( 'Cancel' ) ).toBeInTheDocument();
		expect( getByText( 'Save display order' ) ).toBeInTheDocument();

		expect(
			screen.queryByText( 'Change display order' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Payment methods menu' )
		).not.toBeInTheDocument();
	} );

	it( 'OC is enabled', () => {
		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );

		const { getByText, getByLabelText } = render(
			<SectionHeading isChangingDisplayOrder={ false } />
		);

		expect( getByText( 'Payment methods' ) ).toBeInTheDocument();
		expect( getByLabelText( 'Payment methods menu' ) ).toBeInTheDocument();

		expect(
			screen.queryByText( 'Change display order' )
		).not.toBeInTheDocument();
	} );
} );
