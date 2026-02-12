import { render, screen } from '@testing-library/react';
import {
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';

jest.mock( '@stripe/react-stripe-js/checkout', () => ( {
	CurrencySelectorElement: jest.fn(),
	PaymentElement: jest.fn(),
	useCheckout: jest.fn(),
} ) );

describe( 'CheckoutForm', () => {
	CurrencySelectorElement.mockReturnValue(
		<div>Currency Selector Element</div>
	);
	PaymentElement.mockReturnValue( <div>Payment Element</div> );

	const components = {
		LoadingMask: ( { isLoading, showSpinner, screenReaderLabel } ) => (
			<div>
				{ isLoading && showSpinner && (
					<span>{ screenReaderLabel }</span>
				) }
			</div>
		),
	};
	const onLoadError = jest.fn();

	it( 'should render loading state', () => {
		useCheckout.mockReturnValue( {
			type: 'loading',
		} );

		render(
			<CheckoutForm
				components={ components }
				onLoadError={ onLoadError }
			/>
		);

		expect(
			screen.getByText( 'Loading payment method…' )
		).toBeInTheDocument();
	} );

	it( 'should render error state', () => {
		useCheckout.mockReturnValue( {
			type: 'error',
			error: {
				message: 'Test error',
			},
		} );

		render(
			<CheckoutForm
				components={ components }
				onLoadError={ onLoadError }
			/>
		);

		expect( screen.getByText( 'Error: Test error' ) ).toBeInTheDocument();
	} );

	it( 'should render the payment element', () => {
		useCheckout.mockReturnValue( {
			checkoutState: {
				type: 'success',
				checkout: {
					id: 'test_checkout_id',
				},
			},
		} );

		render(
			<CheckoutForm
				components={ components }
				onLoadError={ onLoadError }
			/>
		);

		expect( screen.getByText( 'Payment Element' ) ).toBeInTheDocument();
	} );
} );
