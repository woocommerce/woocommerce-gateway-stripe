import { render, screen } from '@testing-library/react';
import PaymentRequestSection from '..';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	usePaymentRequestEnabledSettings,
} from 'wcstripe/data';

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
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card', 'link' ] );
	} );

	it( 'renders settings with defaults', () => {
		render( <PaymentRequestSection /> );

		const label = screen.queryByText( 'Apple Pay / Google Pay' );
		expect( label ).toBeInTheDocument();
	} );

	it( 'hide link payment if card payment method is inactive', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'link' ] ] );

		render( <PaymentRequestSection /> );

		expect( screen.queryByText( 'Link by Stripe' ) ).toBeNull();
	} );

	it( 'show link payment if card payment method is active', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		render( <PaymentRequestSection /> );

		expect( screen.queryByText( 'Link by Stripe' ) ).toBeInTheDocument();
	} );

	it( 'test stripe link checkbox checked', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		const container = render( <PaymentRequestSection /> );
		const linkCheckbox = container.getByRole( 'checkbox', {
			name: /Link by Stripe Input/i,
		} );
		expect( linkCheckbox ).toBeChecked();
	} );

	it( 'test stripe link checkbox not checked', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		const container = render( <PaymentRequestSection /> );
		const linkCheckbox = container.getByRole( 'checkbox', {
			name: /Link by Stripe Input/i,
		} );
		expect( linkCheckbox ).not.toBeChecked();
	} );
} );
