import React from 'react';
import { screen, render } from '@testing-library/react';
import AccountActivationNotice from '..';
import { useGetCapabilities } from 'wcstripe/data/account';
import { useGetAvailablePaymentMethodIds } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useGetAvailablePaymentMethodIds: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account', () => ( {
	useGetCapabilities: jest.fn(),
} ) );

describe( 'AccountActivationNotice', () => {
	beforeEach( () => {
		useGetCapabilities.mockReturnValue( {} );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'giropay',
			'eps',
			'card',
		] );
	} );

	it( 'should render notice if any method has "pending" status', () => {
		useGetCapabilities.mockReturnValue( {
			giropay_payments: 'pending',
			eps_payments: 'active',
			card_payments: 'active',
		} );
		render( <AccountActivationNotice /> );

		expect(
			screen.queryByText(
				'Payment methods require activation in your Stripe dashboard.'
			)
		).toBeInTheDocument();
	} );

	it( 'should render notice if any method has "inactive" status', () => {
		useGetCapabilities.mockReturnValue( {
			giropay_payments: 'inactive',
			eps_payments: 'active',
			card_payments: 'active',
		} );
		render( <AccountActivationNotice /> );

		expect(
			screen.queryByText(
				'Payment methods require activation in your Stripe dashboard.'
			)
		).toBeInTheDocument();
	} );

	it( 'should render notice if capability object is empty', () => {
		useGetCapabilities.mockReturnValue( {} );
		render( <AccountActivationNotice /> );

		expect(
			screen.queryByText(
				'Payment methods require activation in your Stripe dashboard.'
			)
		).toBeInTheDocument();
	} );

	it( 'should not render notice if no method has "inactive" or "pending status', () => {
		useGetCapabilities.mockReturnValue( {
			giropay_payments: 'active',
			eps_payments: 'active',
			card_payments: 'active',
		} );
		render( <AccountActivationNotice /> );

		expect(
			screen.queryByText(
				'Payment methods require activation in your Stripe dashboard.'
			)
		).not.toBeInTheDocument();
	} );
} );
