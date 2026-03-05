import {
	usePaymentCompleteHandler,
	usePaymentFailHandler,
} from 'wcstripe/blocks/checkout-sessions/hooks';
import { useEffect } from '@wordpress/element';

jest.mock( '@wordpress/element' );

describe( 'CheckoutSessions hook tests', () => {
	const onCheckoutSuccess = jest.fn();
	beforeEach( () => {
		useEffect.mockImplementation( ( fn ) => fn() );
	} );

	describe( 'usePaymentCompleteHandler hook', () => {
		let onCheckoutSuccessResult; // Store the result from onCheckoutSuccess callback
		beforeEach( () => {
			onCheckoutSuccess.mockImplementation( ( fn ) => {
				const onCheckoutProcessingData = {
					processingResponse: {
						paymentDetails: {
							redirect: 'https://example.com/return-here',
						},
					},
				};
				onCheckoutSuccessResult = fn( onCheckoutProcessingData );
			} );
		} );

		it( 'checkoutState.type is not success', async () => {
			const checkoutState = { type: 'error' };
			usePaymentCompleteHandler( checkoutState, onCheckoutSuccess );
			expect( await onCheckoutSuccessResult ).toEqual( {
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
			usePaymentCompleteHandler( checkoutState, onCheckoutSuccess );
			expect( await onCheckoutSuccessResult ).toEqual( {
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
			usePaymentCompleteHandler( checkoutState, onCheckoutSuccess );
			expect( await onCheckoutSuccessResult ).toEqual( {
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

		it( 'calls onCheckoutFail and returns error object', async () => {
			const checkoutState = {};
			usePaymentFailHandler(
				checkoutState,
				onCheckoutFail,
				emitResponse
			);
			expect( await onCheckoutFailResult ).toEqual( {
				type: 'failure',
				messageContext: 'payments',
				message:
					'An error occurred during payment processing. Please try again.',
			} );
		} );
	} );
} );
