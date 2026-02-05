import { usePaymentCompleteHandler } from 'wcstripe/blocks/checkout-sessions/hooks';
import { useEffect } from '@wordpress/element';

jest.mock( '@wordpress/element' );

describe( 'CheckoutSessions hook tests', () => {
	const onCheckoutSuccess = jest.fn();
	describe( 'usePaymentCompleteHandler hook', () => {
		let onCheckoutSuccessResult; // Store the result from onCheckoutSuccess callback
		beforeEach( () => {
			useEffect.mockImplementation( ( fn ) => fn() );
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

		it( 'checkoutState.type is not success', () => {
			const checkoutState = { type: 'error' };
			usePaymentCompleteHandler( checkoutState, onCheckoutSuccess );
			expect( onCheckoutSuccessResult ).toEqual( {
				type: 'error',
				message: 'Checkout is not ready for confirmation.',
			} );
		} );

		it( 'error confirming the session', () => {
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
			expect( onCheckoutSuccessResult ).toEqual( {
				type: 'error',
				message: 'Test error.',
			} );
		} );

		it( 'success', () => {
			const checkoutState = {
				type: 'success',
				checkout: {
					confirm: () => ( {
						type: 'success',
					} ),
				},
			};
			usePaymentCompleteHandler( checkoutState, onCheckoutSuccess );
			expect( onCheckoutSuccessResult ).toEqual( {
				type: 'success',
			} );
		} );
	} );
} );
