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

jest.mock( 'wcstripe/blocks/checkout-sessions/hooks', () => ( {
	usePaymentSetupHandler: jest.fn(),
	useCheckoutSuccessHandler: jest.fn(),
	usePaymentFailHandler: jest.fn(),
	useCheckoutSessionTotalsSync: jest.fn(),
} ) );

jest.mock(
	'wcstripe/optimized-checkout/handle-display-of-payment-instructions',
	() => ( {
		handleDisplayOfPaymentInstructions: jest.fn(),
	} )
);

describe( 'CheckoutForm', () => {
	const api = { checkoutSessionsUpdateSession: jest.fn() };

	const LoadingMask = ( { isLoading, showSpinner, screenReaderLabel } ) => (
		<div>
			{ isLoading && showSpinner && <span>{ screenReaderLabel }</span> }
		</div>
	);
	const onLoadError = jest.fn();
	const setShouldLoadStripeElements = jest.fn();
	const testingInstructions = 'Test instructions';
	const eventRegistration = {
		onPaymentSetup: jest.fn(),
		onCheckoutSuccess: jest.fn(),
		onCheckoutFail: jest.fn(),
	};
	const emitResponse = {
		noticeContexts: { PAYMENTS: 'payments' },
	};

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
				api={ api }
				emitResponse={ emitResponse }
				eventRegistration={ eventRegistration }
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
				api={ api }
				emitResponse={ emitResponse }
				eventRegistration={ eventRegistration }
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
				api={ api }
				emitResponse={ emitResponse }
				eventRegistration={ eventRegistration }
				LoadingMask={ LoadingMask }
				onLoadError={ onLoadError }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				testingInstructions={ testingInstructions }
			/>
		);

		expect( screen.getByText( 'Payment Element' ) ).toBeInTheDocument();
	} );

	it( 'should render the adaptive pricing disclosure for EEA billing country', () => {
		useCheckout.mockReturnValue( {
			type: 'success',
			checkout: { id: 'test_checkout_id' },
		} );

		render(
			<CheckoutForm
				api={ api }
				billing={ {
					billingAddress: {
						country: 'DE',
					},
				} }
				emitResponse={ emitResponse }
				eventRegistration={ eventRegistration }
				LoadingMask={ LoadingMask }
				onLoadError={ onLoadError }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				testingInstructions={ testingInstructions }
			/>
		);

		expect(
			screen.getByText( '(Includes 3.8% conversion service).', {
				exact: false,
			} )
		).toBeInTheDocument();
	} );

	it( 'should not render the adaptive pricing disclosure for non-EEA billing country', () => {
		useCheckout.mockReturnValue( {
			type: 'success',
			checkout: { id: 'test_checkout_id' },
		} );

		render(
			<CheckoutForm
				api={ api }
				billing={ {
					billingAddress: {
						country: 'US',
					},
				} }
				emitResponse={ emitResponse }
				eventRegistration={ eventRegistration }
				LoadingMask={ LoadingMask }
				onLoadError={ onLoadError }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				testingInstructions={ testingInstructions }
			/>
		);

		expect(
			screen.queryByText( '(Includes 3.8% conversion service).', {
				exact: false,
			} )
		).not.toBeInTheDocument();
	} );

	it( 'should not render the adaptive pricing disclosure when billing country is absent', () => {
		useCheckout.mockReturnValue( {
			type: 'success',
			checkout: { id: 'test_checkout_id' },
		} );

		render(
			<CheckoutForm
				api={ api }
				billing={ {
					billingAddress: {
						country: '',
					},
				} }
				emitResponse={ emitResponse }
				eventRegistration={ eventRegistration }
				LoadingMask={ LoadingMask }
				onLoadError={ onLoadError }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				testingInstructions={ testingInstructions }
			/>
		);

		expect(
			screen.queryByText( '(Includes 3.8% conversion service).', {
				exact: false,
			} )
		).not.toBeInTheDocument();
	} );
} );
