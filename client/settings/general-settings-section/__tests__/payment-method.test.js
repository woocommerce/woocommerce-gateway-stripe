import React from 'react';
import { screen, render } from '@testing-library/react';
import icons from '../../../payment-method-icons';
import PaymentMethod from '../payment-method';
import PaymentMethodDescription from '../payment-method-description';
import { useEnabledPaymentMethodIds, useManualCapture } from 'wcstripe/data';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_SEPA,
	PAYMENT_METHOD_UNAVAILABLE_REASONS,
} from 'wcstripe/stripe-utils/constants';
import usePaymentMethodUnavailableReason from 'utils/use-payment-method-unavailable-reason';
import { getFormattedPaymentMethodDescription } from 'wcstripe/settings/general-settings-section/get-formatted-payment-method-description';

jest.mock( '../payment-method-description' );
jest.mock( 'wcstripe/data', () => ( {
	...jest.requireActual( 'wcstripe/data' ),
	useManualCapture: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
} ) );
jest.mock( 'utils/use-payment-method-unavailable-reason' );
jest.mock(
	'wcstripe/settings/general-settings-section/get-formatted-payment-method-description',
	() => ( {
		getFormattedPaymentMethodDescription: jest.fn(),
	} )
);

describe( 'PaymentMethod', () => {
	const globalSettingsParams = global.wc_stripe_settings_params;

	beforeEach( () => {
		jest.clearAllMocks();

		global.wc_stripe_settings_params = {
			...globalSettingsParams,
			are_apms_deprecated: false,
		};

		useManualCapture.mockReturnValue( [ false ] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
			jest.fn(),
		] );
		usePaymentMethodUnavailableReason.mockReturnValue( null );

		PaymentMethodDescription.mockReturnValue(
			<div data-testid="payment-method-description" />
		);
	} );

	const renderPaymentMethod = ( method, data ) => {
		render( <PaymentMethod method={ method } data={ data } /> );
	};

	it( 'card payment method should be enabled with expected details', () => {
		const data = {
			account: {
				default_currency: 'USD',
			},
		};

		const mockDescription = 'TEST credit card description';
		getFormattedPaymentMethodDescription.mockReturnValue( mockDescription );

		renderPaymentMethod( PAYMENT_METHOD_CARD, data );

		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: 'Credit card / debit card',
		} );
		expect( cardCheckbox ).toBeInTheDocument();
		expect( cardCheckbox ).toBeEnabled();
		expect( cardCheckbox ).toBeChecked();

		expect(
			screen.getByTestId( 'payment-method-description' )
		).toBeInTheDocument();
		expect( PaymentMethodDescription ).toHaveBeenCalledWith(
			expect.objectContaining( {
				id: PAYMENT_METHOD_CARD,
				Icon: icons.card,
				description: mockDescription,
				label: 'Credit card / debit card',
				deprecated: false,
				supportsRecurring: true,
			} ),
			expect.any( Object )
		);
	} );

	it( 'SEPA payment method should be disabled when payment method is not enabled and not available', () => {
		const data = {
			account: {
				default_currency: 'USD',
			},
		};

		const mockDescription = 'TEST SEPA description';
		getFormattedPaymentMethodDescription.mockReturnValue( mockDescription );
		usePaymentMethodUnavailableReason.mockReturnValue(
			PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY
		);

		renderPaymentMethod( PAYMENT_METHOD_SEPA, data );

		const checkbox = screen.getByRole( 'checkbox', {
			name: 'Direct debit payment',
		} );
		expect( checkbox ).toBeInTheDocument();
		expect( checkbox ).toBeDisabled();
		expect( checkbox ).not.toBeChecked();

		expect(
			screen.getByTestId( 'payment-method-description' )
		).toBeInTheDocument();
		expect( PaymentMethodDescription ).toHaveBeenCalledWith(
			expect.objectContaining( {
				id: PAYMENT_METHOD_SEPA,
				Icon: icons.sepa_debit,
				description: mockDescription,
				label: 'Direct debit payment',
				deprecated: false,
				supportsRecurring: true,
			} ),
			expect.any( Object )
		);
	} );

	it( 'SEPA payment method should be enabled when payment method is enabled and not available', () => {
		const data = {
			account: {
				default_currency: 'USD',
			},
		};

		const mockDescription = 'TEST SEPA description';
		getFormattedPaymentMethodDescription.mockReturnValue( mockDescription );

		usePaymentMethodUnavailableReason.mockReturnValue(
			PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY
		);
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_SEPA ],
			jest.fn(),
		] );

		renderPaymentMethod( PAYMENT_METHOD_SEPA, data );

		const checkbox = screen.getByRole( 'checkbox', {
			name: 'Direct debit payment',
		} );
		expect( checkbox ).toBeInTheDocument();
		expect( checkbox ).toBeEnabled();
		expect( checkbox ).toBeChecked();

		expect(
			screen.getByTestId( 'payment-method-description' )
		).toBeInTheDocument();
		expect( PaymentMethodDescription ).toHaveBeenCalledWith(
			expect.objectContaining( {
				id: PAYMENT_METHOD_SEPA,
				Icon: icons.sepa_debit,
				description: mockDescription,
				label: 'Direct debit payment',
				deprecated: false,
				supportsRecurring: true,
			} ),
			expect.any( Object )
		);
	} );
} );
