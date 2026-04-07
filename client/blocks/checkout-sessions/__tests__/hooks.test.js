import {
	usePaymentSetupHandler,
	useCheckoutSuccessHandler,
	usePaymentFailHandler,
} from 'wcstripe/blocks/checkout-sessions/hooks';
import { useEffect } from '@wordpress/element';
import { select } from '@wordpress/data';

jest.mock( '@wordpress/element' );
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );

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
		const checkoutSessionId = 'cs_test_123';

		beforeEach( () => {
			document.body.innerHTML = '';
			onPaymentSetup.mockImplementation( ( fn ) => {
				onPaymentSetupResultPromise = fn();
			} );
		} );

		it( 'returns error when hasLoadErrorRef.current is true', async () => {
			const hasLoadErrorRef = { current: true };
			usePaymentSetupHandler(
				onPaymentSetup,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				true
			);
			const result = await onPaymentSetupResultPromise;
			expect( result ).toEqual( {
				type: 'error',
				message:
					'There was an error loading the payment information. Please refresh the page and try again.',
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
						save_payment_method: 'no',
						wc_stripe_checkout_session_id: checkoutSessionId,
					},
				},
			} );
		} );

		it( 'returns save_payment_method yes when the Blocks save checkbox is checked', async () => {
			document.body.innerHTML = `
				<div class="wc-block-components-payment-methods__save-card-info">
					<input type="checkbox" checked />
				</div>
			`;
			const hasLoadErrorRef = { current: false };
			usePaymentSetupHandler(
				onPaymentSetup,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				true
			);
			const result = await onPaymentSetupResultPromise;
			expect( result.meta.paymentMethodData.save_payment_method ).toBe(
				'yes'
			);
		} );
	} );

	describe( 'useCheckoutSuccessHandler hook', () => {
		let onCheckoutSuccessResultPromise;
		const onCheckoutSuccess = jest.fn();

		beforeEach( () => {
			document.body.innerHTML = '';
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

		it( 'confirm passes savePaymentMethod true when save checkbox is checked', async () => {
			document.body.innerHTML = `
				<div class="wc-block-components-payment-methods__save-card-info">
					<input type="checkbox" checked />
				</div>
			`;
			const confirm = jest.fn().mockResolvedValue( {
				type: 'success',
			} );
			const checkoutState = {
				type: 'success',
				checkout: { confirm },
			};
			useCheckoutSuccessHandler( checkoutState, onCheckoutSuccess );
			await onCheckoutSuccessResultPromise;
			expect( confirm ).toHaveBeenCalledWith( {
				returnUrl: 'https://example.com/return-here',
				redirect: 'if_required',
				savePaymentMethod: true,
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
