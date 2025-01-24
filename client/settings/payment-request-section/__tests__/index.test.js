import { render, screen } from '@testing-library/react';
import PaymentRequestSection from '..';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	usePaymentRequestEnabledSettings,
	useAmazonPayEnabledSettings,
} from 'wcstripe/data';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_LINK,
} from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useAmazonPayEnabledSettings: jest.fn(),
} ) );

const getMockPaymentRequestEnabledSettings = (
	isEnabled,
	updateIsPaymentRequestEnabledHandler
) => [ isEnabled, updateIsPaymentRequestEnabledHandler ];

describe( 'PaymentRequestSection', () => {
	const globalValues = global.wc_stripe_settings_params;

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
		useAmazonPayEnabledSettings.mockReturnValue( [ false, jest.fn() ] );
		global.wc_stripe_settings_params = {
			...globalValues,
			is_ece_enabled: true,
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_settings_params = globalValues;
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

	it( 'hide Amazon Pay if ECE is disabled', () => {
		global.wc_stripe_settings_params = {
			...globalValues,
			is_ece_enabled: false,
		};

		render( <PaymentRequestSection /> );

		expect( screen.queryByText( 'Amazon Pay' ) ).toBeNull();
	} );

	it( 'test Amazon Pay checkbox not checked', () => {
		const container = render( <PaymentRequestSection /> );
		const amazonPayCheckbox = container.getByRole( 'checkbox', {
			name: /Amazon Pay Input/i,
		} );
		expect( amazonPayCheckbox ).not.toBeChecked();
	} );

	it( 'test Amazon Pay checkbox checked', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		const container = render( <PaymentRequestSection /> );
		const amazonPayCheckbox = container.getByRole( 'checkbox', {
			name: /Amazon Pay Input/i,
		} );
		expect( amazonPayCheckbox ).toBeChecked();
	} );
} );
