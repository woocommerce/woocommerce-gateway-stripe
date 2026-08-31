import { render, screen } from '@testing-library/react';
import PaymentRequestSection from '..';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useExpressCheckoutEnabledSettings,
	useAmazonPayEnabledSettings,
	useIsOCEnabled,
	useIsAdaptivePricingEnabled,
} from 'wcstripe/data';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_LINK,
	PAYMENT_METHOD_AMAZON_PAY,
} from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/data', () => ( {
	useExpressCheckoutEnabledSettings: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useAmazonPayEnabledSettings: jest.fn(),
	useIsOCEnabled: jest.fn(),
	useIsAdaptivePricingEnabled: jest.fn(),
} ) );

const getMockPaymentRequestEnabledSettings = (
	isEnabled,
	updateIsPaymentRequestEnabledHandler
) => [ isEnabled, updateIsPaymentRequestEnabledHandler ];

describe( 'PaymentRequestSection', () => {
	const globalValues = global.wc_stripe_settings_params;
	const globalSettings = global.wcSettings;

	beforeEach( () => {
		useExpressCheckoutEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( true, jest.fn() )
		);
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
			jest.fn(),
		] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_LINK,
			PAYMENT_METHOD_AMAZON_PAY,
		] );
		useAmazonPayEnabledSettings.mockReturnValue( [ false, jest.fn() ] );
		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		useIsAdaptivePricingEnabled.mockReturnValue( [ false, jest.fn() ] );
		global.wc_stripe_settings_params = {
			...globalValues,
			taxes_based_on_billing: false,
			is_card_method_enabled: true,
		};
		global.wcSettings = {
			...globalSettings,
			currency: { code: 'USD' },
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_settings_params = globalValues;
		global.wcSettings = globalSettings;
	} );

	it( 'renders settings with defaults', () => {
		render( <PaymentRequestSection /> );

		const label = screen.queryByText( 'Apple Pay / Google Pay' );
		expect( label ).toBeInTheDocument();
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

	it( 'render Amazon Pay if it is an available payment method', () => {
		render( <PaymentRequestSection /> );

		expect( screen.queryByText( 'Amazon Pay' ) ).toBeInTheDocument();
	} );

	it( 'hide Amazon Pay if it is not an available payment method', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_LINK,
		] );

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

	it( 'Amazon Pay checkbox disabled', () => {
		global.wc_stripe_settings_params = {
			...globalValues,
			taxes_based_on_billing: true,
		};

		const container = render( <PaymentRequestSection /> );
		const amazonPayCheckbox = container.getByRole( 'checkbox', {
			name: /Amazon Pay Input/i,
		} );
		expect( amazonPayCheckbox ).toBeDisabled();
	} );

	it( 'Amazon Pay checkbox enabled when the base currency is unsupported', () => {
		global.wcSettings = {
			...globalSettings,
			currency: { code: 'CHF' },
		};

		const container = render( <PaymentRequestSection /> );
		const amazonPayCheckbox = container.getByRole( 'checkbox', {
			name: /Amazon Pay Input/i,
		} );
		expect( amazonPayCheckbox ).toBeEnabled();
		expect( screen.getByText( 'Requires currency' ) ).toBeVisible();
	} );

	it( 'Apple Pay / Google Pay checkbox disabled', () => {
		useExpressCheckoutEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( false, jest.fn() )
		);
		global.wc_stripe_settings_params = {
			...globalValues,
			is_card_method_enabled: false,
		};

		const container = render( <PaymentRequestSection /> );
		const eceCheckbox = container.getByRole( 'checkbox', {
			name: /Apple Pay \/ Google Pay Input/i,
		} );
		expect( eceCheckbox ).toBeDisabled();
	} );

	it( 'Link checkbox disabled', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
			jest.fn(),
		] );
		global.wc_stripe_settings_params = {
			...globalValues,
			is_card_method_enabled: false,
		};

		const container = render( <PaymentRequestSection /> );
		const linkCheckbox = container.getByRole( 'checkbox', {
			name: /Link by Stripe Input/i,
		} );
		expect( linkCheckbox ).toBeDisabled();
	} );
} );
