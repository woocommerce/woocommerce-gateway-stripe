import {
	usePaymentSetupHandler,
	useCheckoutSuccessHandler,
	usePaymentFailHandler,
} from 'wcstripe/blocks/checkout-sessions/hooks';
import { useEffect } from '@wordpress/element';
import { select } from '@wordpress/data';

jest.mock( '@wordpress/element' );

describe( 'CheckoutSessions hook tests', () => {
	beforeEach( () => {
		useEffect.mockImplementation( ( fn ) => fn() );
	} );

	afterEach( () => {
		delete window.wc;
	} );

	describe( 'usePaymentSetupHandler hook', () => {
		let onPaymentSetupResultPromise;
		const onPaymentSetup = jest.fn();
		const billingAddress = {
			email: 'test@example.com',
			first_name: 'John',
			last_name: 'Doe',
			address_1: '123 Main St',
			address_2: '',
			city: 'Anytown',
			state: 'CA',
			postcode: '12345',
			country: 'US',
		};
		const checkoutSessionId = 'cs_test_123';

		beforeEach( () => {
			onPaymentSetup.mockImplementation( ( fn ) => {
				onPaymentSetupResultPromise = fn();
			} );
		} );

		it( 'returns error when hasLoadErrorRef.current is true', async () => {
			const hasLoadErrorRef = { current: true };
			usePaymentSetupHandler(
				onPaymentSetup,
				billingAddress,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				true
			);
			const result = await onPaymentSetupResultPromise;
			expect( result ).toEqual( {
				type: 'error',
				message:
					'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.',
			} );
		} );

		it( 'returns undefined when there are validation errors', async () => {
			window.wc = {
				wcBlocksData: { validationStore: 'wc/store/validation' },
			};
			select.mockReturnValue( { hasValidationErrors: () => true } );

			const hasLoadErrorRef = { current: false };
			usePaymentSetupHandler(
				onPaymentSetup,
				billingAddress,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				true
			);
			const result = await onPaymentSetupResultPromise;
			expect( result ).toBeUndefined();
		} );

		it( 'returns error when payment element is incomplete', async () => {
			const hasLoadErrorRef = { current: false };
			usePaymentSetupHandler(
				onPaymentSetup,
				billingAddress,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				false
			);
			const result = await onPaymentSetupResultPromise;
			expect( result ).toEqual( {
				type: 'error',
				message: 'Your payment information is incomplete.',
			} );
		} );

		it( 'returns error when errorMessage is set', async () => {
			const hasLoadErrorRef = { current: false };
			usePaymentSetupHandler(
				onPaymentSetup,
				billingAddress,
				checkoutSessionId,
				'Payment method error',
				hasLoadErrorRef,
				true
			);
			const result = await onPaymentSetupResultPromise;
			expect( result ).toEqual( {
				type: 'error',
				message: 'Payment method error',
			} );
		} );

		it( 'returns success with payment method data', async () => {
			const hasLoadErrorRef = { current: false };
			usePaymentSetupHandler(
				onPaymentSetup,
				billingAddress,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				true
			);
			const result = await onPaymentSetupResultPromise;
			expect( result ).toEqual( {
				type: 'success',
				meta: {
					paymentMethodData: {
						payment_method: 'stripe',
						'wc-stripe-is-deferred-intent': true,
						save_payment_method: 'no',
						wc_stripe_checkout_session_id: checkoutSessionId,
						billing_email: billingAddress.email,
						billing_first_name: billingAddress.first_name,
						billing_last_name: billingAddress.last_name,
						billing_address_1: billingAddress.address_1,
						billing_address_2: billingAddress.address_2,
						billing_city: billingAddress.city,
						billing_state: billingAddress.state,
						billing_postcode: billingAddress.postcode,
						billing_country: billingAddress.country,
					},
				},
			} );
		} );
	} );

	describe( 'useCheckoutSuccessHandler hook', () => {
		let onCheckoutSuccessResultPromise;
		const onCheckoutSuccess = jest.fn();

		beforeEach( () => {
			onCheckoutSuccess.mockImplementation( ( fn ) => {
				const onCheckoutProcessingData = {
					processingResponse: {
						paymentDetails: {
							redirect: 'https://example.com/return-here',
						},
					},
				};
				onCheckoutSuccessResultPromise = fn( onCheckoutProcessingData );
			} );
		} );

		it( 'checkoutState.type is not success', async () => {
			const checkoutState = { type: 'error' };
			useCheckoutSuccessHandler( checkoutState, onCheckoutSuccess );
			expect( await onCheckoutSuccessResultPromise ).toEqual( {
				type: 'error',
				message: 'Checkout is not ready for confirmation.',
			} );
		} );

		it( 'error confirming the session', async () => {
			const checkoutState = {
				type: 'success',
				checkout: {
					confirm: () => ( {
						type: 'error',
						error: { message: 'Test error.' },
					} ),
				},
			};
			useCheckoutSuccessHandler( checkoutState, onCheckoutSuccess );
			expect( await onCheckoutSuccessResultPromise ).toEqual( {
				type: 'error',
				message: 'Test error.',
			} );
		} );

		it( 'success', async () => {
			const checkoutState = {
				type: 'success',
				checkout: {
					confirm: () => ( {
						type: 'success',
					} ),
				},
			};
			useCheckoutSuccessHandler( checkoutState, onCheckoutSuccess );
			expect( await onCheckoutSuccessResultPromise ).toEqual( {
				type: 'success',
			} );
		} );
	} );

	describe( 'usePaymentFailHandler hook', () => {
		let onCheckoutFailResult; // Store the result from onCheckoutFail callback
		const onCheckoutFail = jest.fn();
		const emitResponse = {
			noticeContexts: {
				PAYMENTS: 'payments',
			},
		};

		beforeEach( () => {
			onCheckoutFail.mockImplementation( ( fn ) => {
				const onCheckoutProcessingData = {
					processingResponse: {
						paymentDetails: {
							errorMessage:
								'An error occurred during payment processing. Please try again.',
						},
					},
				};
				onCheckoutFailResult = fn( onCheckoutProcessingData );
			} );
		} );

		it( 'calls onCheckoutFail and returns error object', () => {
			usePaymentFailHandler( onCheckoutFail, emitResponse );
			expect( onCheckoutFailResult ).toEqual( {
				type: 'failure',
				messageContext: 'payments',
				message:
					'An error occurred during payment processing. Please try again.',
			} );
		} );
	} );
} );
