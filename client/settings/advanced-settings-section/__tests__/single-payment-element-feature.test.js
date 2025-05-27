import { render, screen } from '@testing-library/react';
import React, { useContext } from 'react';
import userEvent from '@testing-library/user-event';
import SinglePaymentElementFeature from 'wcstripe/settings/advanced-settings-section/single-payment-element-feature';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

jest.useFakeTimers();

describe( 'Single Payment Element feature setting', () => {
	it( 'should render', () => {
		render( <SinglePaymentElementFeature /> );

		expect(
			screen.queryByText(
				'Enable Optimized Checkout Suite (recommended)'
			)
		).toBeInTheDocument();
	} );

	it( 'should be disabled when UPE is disabled', () => {
		const UpdateUpeDisabledFlagMock = () => {
			const { setIsUpeEnabled } = useContext( UpeToggleContext );
			setTimeout( () => {
				setIsUpeEnabled( false );
			}, 1000 );
			return null;
		};

		render(
			<div>
				<UpdateUpeDisabledFlagMock />
				<SinglePaymentElementFeature />
			</div>
		);

		const checkbox = screen.getByTestId(
			'single-payment-element-checkbox'
		);

		userEvent.click( checkbox );

		jest.runAllTimers();

		expect( checkbox ).toBeDisabled();
		expect( checkbox ).not.toBeChecked();
	} );
} );
