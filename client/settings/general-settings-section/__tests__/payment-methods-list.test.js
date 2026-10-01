import React, { useState } from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaymentMethodsList from '../payment-methods-list';
import {
	useGetOrderedPaymentMethodIds,
	useIsAdaptivePricingEnabled,
	useIsOCEnabled,
	useManualCapture,
} from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';
import getPaymentMethodUnavailableReason from 'utils/get-payment-method-unavailable-reason';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_EPS,
	PAYMENT_METHOD_KLARNA,
	PAYMENT_METHOD_UNAVAILABLE_REASONS,
} from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/data', () => ( {
	useGetOrderedPaymentMethodIds: jest.fn(),
	useIsAdaptivePricingEnabled: jest.fn(),
	useIsOCEnabled: jest.fn(),
	useManualCapture: jest.fn(),
} ) );
jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
	useGetCapabilities: jest.fn().mockReturnValue( {} ),
} ) );
jest.mock( '@woocommerce/settings', () => ( {
	getSetting: jest.fn().mockReturnValue( { code: 'EUR' } ),
} ) );
jest.mock( 'utils/get-payment-method-unavailable-reason' );

// The test copy of @wordpress/components may predate accessibleWhenDisabled,
// so accept either way of marking a button disabled.
const isDisabled = ( button ) =>
	button.disabled || button.getAttribute( 'aria-disabled' ) === 'true';

describe( 'PaymentMethodsList when changing the display order', () => {
	let setOrderedPaymentMethodIds;

	beforeEach( () => {
		setOrderedPaymentMethodIds = jest.fn();
		useGetOrderedPaymentMethodIds.mockImplementation( () => {
			const [ ids, setIds ] = useState( [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_EPS,
				PAYMENT_METHOD_KLARNA,
				// Unmapped IDs are not rendered and must not count as an end.
				'unmapped_method',
			] );
			return {
				orderedPaymentMethodIds: ids,
				setOrderedPaymentMethodIds: ( next ) => {
					setOrderedPaymentMethodIds( next );
					setIds( next );
				},
			};
		} );
		getPaymentMethodUnavailableReason.mockReturnValue( null );
		useIsAdaptivePricingEnabled.mockReturnValue( [ false ] );
		useIsOCEnabled.mockReturnValue( [ false ] );
		useManualCapture.mockReturnValue( [ false ] );
		useAccount.mockReturnValue( { data: {} } );
	} );

	const getButton = ( name ) => screen.getByRole( 'button', { name } );

	const getRowLabels = () =>
		screen
			.getAllByRole( 'listitem' )
			.map( ( row ) =>
				row.querySelector( 'button' ).getAttribute( 'aria-label' )
			);

	it( 'disables moving the first row up and the last visible row down', () => {
		render( <PaymentMethodsList isChangingDisplayOrder /> );

		expect(
			isDisabled( getButton( 'Move Credit card / debit card up' ) )
		).toBe( true );
		expect( isDisabled( getButton( 'Move Klarna down' ) ) ).toBe( true );

		expect( isDisabled( getButton( 'Move EPS up' ) ) ).toBe( false );
		expect( isDisabled( getButton( 'Move EPS down' ) ) ).toBe( false );
	} );

	it( 'does not change the order when a disabled button is pressed', async () => {
		render( <PaymentMethodsList isChangingDisplayOrder /> );

		await userEvent.click(
			getButton( 'Move Credit card / debit card up' )
		);
		await userEvent.click( getButton( 'Move Klarna down' ) );

		expect( setOrderedPaymentMethodIds ).not.toHaveBeenCalled();
	} );

	it( 'moves a payment method up', async () => {
		render( <PaymentMethodsList isChangingDisplayOrder /> );

		await userEvent.click( getButton( 'Move EPS up' ) );

		expect( setOrderedPaymentMethodIds ).toHaveBeenCalledWith( [
			'eps',
			'card',
			'klarna',
			'unmapped_method',
		] );
		expect( getRowLabels() ).toEqual( [
			'Move EPS up',
			'Move Credit card / debit card up',
			'Move Klarna up',
		] );
	} );

	it( 'moves a payment method down', async () => {
		render( <PaymentMethodsList isChangingDisplayOrder /> );

		await userEvent.click(
			getButton( 'Move Credit card / debit card down' )
		);

		expect( setOrderedPaymentMethodIds ).toHaveBeenCalledWith( [
			'eps',
			'card',
			'klarna',
			'unmapped_method',
		] );
	} );

	it( 'only moves a payment method within its availability group', async () => {
		getPaymentMethodUnavailableReason.mockImplementation(
			( { paymentMethodId } ) =>
				paymentMethodId === PAYMENT_METHOD_KLARNA
					? PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY
					: null
		);
		render( <PaymentMethodsList isChangingDisplayOrder /> );

		expect( isDisabled( getButton( 'Move EPS down' ) ) ).toBe( true );
		expect( isDisabled( getButton( 'Move Klarna up' ) ) ).toBe( true );

		await userEvent.click( getButton( 'Move EPS down' ) );
		await userEvent.click( getButton( 'Move Klarna up' ) );

		expect( setOrderedPaymentMethodIds ).not.toHaveBeenCalled();
	} );

	it( 'reorders with the keyboard only', async () => {
		render( <PaymentMethodsList isChangingDisplayOrder /> );

		getButton( 'Move Klarna up' ).focus();
		await userEvent.keyboard( '{Enter}' );
		await userEvent.keyboard( '{Enter}' );

		expect( getRowLabels() ).toEqual( [
			'Move Klarna up',
			'Move Credit card / debit card up',
			'Move EPS up',
		] );
	} );
} );
