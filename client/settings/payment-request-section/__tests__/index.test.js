import { render, screen } from '@testing-library/react';
import PaymentRequestSection from '..';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	usePaymentRequestEnabledSettings,
} from 'wcstripe/data';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_LINK,
} from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
} ) );

const getMockPaymentRequestEnabledSettings = (
	isEnabled,
	updateIsPaymentRequestEnabledHandler
) => [ isEnabled, updateIsPaymentRequestEnabledHandler ];

describe( 'PaymentRequestSection', () => {
	beforeEach( () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( true, jest.fn() )
		);
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
			jest.fn(),
		] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_LINK,
		] );
	} );

	it( 'renders settings with defaults', () => {
		render( <PaymentRequestSection /> );

		const label = screen.queryByText( 'Apple Pay / Google Pay' );
		expect( label ).toBeInTheDocument();
	} );

	it( 'hide link payment if card payment method is inactive', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_LINK,
			PAYMENT_METHOD_CARD,
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_LINK ],
		] );

		render( <PaymentRequestSection /> );

		expect( screen.queryByText( 'Link by Stripe' ) ).toBeNull();
	} );

	it( 'show link payment if card payment method is active', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_LINK,
			PAYMENT_METHOD_CARD,
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD, PAYMENT_METHOD_LINK ],
		] );

		render( <PaymentRequestSection /> );

		expect( screen.queryByText( 'Link by Stripe' ) ).toBeInTheDocument();
	} );

	it( 'test stripe link checkbox checked', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_LINK,
			PAYMENT_METHOD_CARD,
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD, PAYMENT_METHOD_LINK ],
		] );

		const container = render( <PaymentRequestSection /> );
		const linkCheckbox = container.getByRole( 'checkbox', {
			name: /Link by Stripe Input/i,
		} );
		expect( linkCheckbox ).toBeChecked();
	} );

	it( 'test stripe link checkbox not checked', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_LINK,
			PAYMENT_METHOD_CARD,
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
		] );

		const container = render( <PaymentRequestSection /> );
		const linkCheckbox = container.getByRole( 'checkbox', {
			name: /Link by Stripe Input/i,
		} );
		expect( linkCheckbox ).not.toBeChecked();
	} );
} );
