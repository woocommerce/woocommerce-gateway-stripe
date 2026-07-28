import { act, render, screen } from '@testing-library/react';
import {
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';
import {
	getBlocksConfiguration,
	getStripeElementOptions,
} from 'wcstripe/blocks/utils';
import { handleDisplayOfSavingCheckbox } from 'wcstripe/optimized-checkout/handle-display-of-saving-checkbox';

jest.mock( '@stripe/react-stripe-js/checkout', () => ( {
	CurrencySelectorElement: jest.fn(),
	PaymentElement: jest.fn(),
	useCheckout: jest.fn(),
} ) );

jest.mock( 'wcstripe/blocks/utils', () => ( {
	getBlocksConfiguration: jest.fn(),
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

jest.mock(
	'wcstripe/optimized-checkout/handle-display-of-saving-checkbox',
	() => ( {
		handleDisplayOfSavingCheckbox: jest.fn(),
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
	const paymentMethodsConfig = {
		card: {
			showSaveOptionByMethod: {
				card: false,
				ideal: false,
				sepa_debit: true,
			},
		},
	};

	beforeEach( () => {
		jest.clearAllMocks();
		getBlocksConfiguration.mockReturnValue( { paymentMethodsConfig } );
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

	/**
	 * The Adaptive Pricing form renders its own Payment Element rather than
	 * PaymentProcessor, so it must hide the store-level save checkbox on mount
	 * (e.g. card with Link enabled), matching the PaymentProcessor path.
	 */
	it( 'evaluates the save checkbox on mount with the default card method', () => {
		useCheckout.mockReturnValue( {
			type: 'success',
			checkout: { id: 'test_checkout_id' },
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

		expect( handleDisplayOfSavingCheckbox ).toHaveBeenCalledWith(
			'card',
			paymentMethodsConfig
		);
	} );

	/**
	 * Switching the Payment Element to a non-reusable sub-method must run the
	 * shared hide/clear helper so the checkbox does not stay visible/checked.
	 */
	it( 'evaluates the save checkbox when the selected method changes', async () => {
		useCheckout.mockReturnValue( {
			type: 'success',
			checkout: { id: 'test_checkout_id' },
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

		const { onChange } = PaymentElement.mock.calls[ 0 ][ 0 ];
		handleDisplayOfSavingCheckbox.mockClear();

		await act( async () => {
			onChange( { value: { type: 'ideal' }, complete: false } );
		} );

		expect( handleDisplayOfSavingCheckbox ).toHaveBeenCalledWith(
			'ideal',
			paymentMethodsConfig
		);
	} );
} );
