import { render, screen } from '@testing-library/react';
import {
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';
import { getStripeElementOptions } from 'wcstripe/blocks/utils';

jest.mock( '@stripe/react-stripe-js/checkout', () => ( {
	CurrencySelectorElement: jest.fn(),
	PaymentElement: jest.fn(),
	useCheckout: jest.fn(),
} ) );

jest.mock( 'wcstripe/blocks/utils', () => ( {
	getStripeElementOptions: jest.fn(),
} ) );

describe( 'CheckoutForm', () => {
	const LoadingMask = ( { isLoading, showSpinner, screenReaderLabel } ) => (
		<div>
			{ isLoading && showSpinner && <span>{ screenReaderLabel }</span> }
		</div>
	);
	const onLoadError = jest.fn();
	const setShouldLoadStripeElements = jest.fn();
	const testingInstructions = 'Test instructions';

	beforeEach( () => {
		CurrencySelectorElement.mockReturnValue(
			<div>Currency Selector Element</div>
		);
		PaymentElement.mockReturnValue( <div>Payment Element</div> );
		getStripeElementOptions.mockReturnValue( {
			fields: {
				billingDetails: {
					name: 'never',
					email: 'never',
					phone: 'auto',
					address: {
						country: 'never',
						line1: 'never',
						line2: 'never',
						city: 'never',
						state: 'never',
						postalCode: 'never',
					},
				},
			},
		} );
	} );

	it( 'should render loading state', () => {
		useCheckout.mockReturnValue( {
			type: 'loading',
		} );

		render(
			<CheckoutForm
				LoadingMask={ LoadingMask }
				onLoadError={ onLoadError }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				testingInstructions={ testingInstructions }
			/>
		);

		expect(
			screen.getByText( 'Loading payment method…' )
		).toBeInTheDocument();
	} );

	it( 'should render error state and call the fallback function', () => {
		useCheckout.mockReturnValue( {
			type: 'error',
			error: {
				message: 'Test error',
			},
		} );

		render(
			<CheckoutForm
				LoadingMask={ LoadingMask }
				onLoadError={ onLoadError }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				testingInstructions={ testingInstructions }
			/>
		);

		expect( screen.getByText( 'Error: Test error' ) ).toBeInTheDocument();
		expect( setShouldLoadStripeElements ).toHaveBeenCalledWith( true );
	} );

	it( 'should render the payment element', () => {
		useCheckout.mockReturnValue( {
			type: 'success',
			checkout: {
				id: 'test_checkout_id',
			},
		} );

		render(
			<CheckoutForm
				LoadingMask={ LoadingMask }
				onLoadError={ onLoadError }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				testingInstructions={ testingInstructions }
			/>
		);

		expect( screen.getByText( 'Payment Element' ) ).toBeInTheDocument();
	} );
} );
