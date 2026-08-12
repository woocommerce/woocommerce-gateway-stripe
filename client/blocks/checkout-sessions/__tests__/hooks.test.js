import { renderHook, waitFor } from '@testing-library/react';
import jQuery from 'jquery';
import {
	usePaymentSetupHandler,
	useCheckoutSuccessHandler,
	usePaymentFailHandler,
	useCheckoutSessionTotalsSync,
} from 'wcstripe/blocks/checkout-sessions/hooks';
import { useEffect } from '@wordpress/element';
import { dispatch, select } from '@wordpress/data';
import { waitForPaymentElementCompletion } from 'wcstripe/blocks/wait-for-payment-element-completion';

jest.mock( '@wordpress/element', () => ( {
	...jest.requireActual( '@wordpress/element' ),
	useEffect: jest.fn( ( fn ) => fn() ),
} ) );

jest.mock( '@wordpress/data', () => {
	const createErrorNotice = jest.fn();
	const removeNotice = jest.fn();
	return {
		select: jest.fn(),
		dispatch: jest.fn( () => ( { createErrorNotice, removeNotice } ) ),
	};
} );

const STALE_TOTAL_MESSAGE =
	"We couldn't update your order total. Please refresh the page and try again.";

jest.mock( 'wcstripe/blocks/wait-for-payment-element-completion', () => ( {
	waitForPaymentElementCompletion: jest.fn(),
} ) );

// hooks.js imports jQuery; mock the module with a chainable so the
// blockUI/unblockUI calls in the totals-sync effect are no-ops here and do not
// abort the re-price under test.
jest.mock( 'jquery', () => {
	const jq = jest.fn( () => {
		const chain = {
			on: jest.fn(),
			trigger: jest.fn(),
			addClass: jest.fn( () => chain ),
			removeClass: jest.fn( () => chain ),
			block: jest.fn( () => chain ),
			unblock: jest.fn( () => chain ),
		};
		return chain;
	} );
	return jq;
} );

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

		it( 'returns error when the totals resync failed (session stale)', async () => {
			const hasLoadErrorRef = { current: false };
			const syncFailedRef = { current: true };
			usePaymentSetupHandler(
				onPaymentSetup,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				true,
				'',
				null,
				syncFailedRef
			);
			const result = await onPaymentSetupResultPromise;
			expect( result ).toEqual( {
				type: 'error',
				message: STALE_TOTAL_MESSAGE,
			} );
		} );

		it( 'does not block when the stale-session ref is clear', async () => {
			const hasLoadErrorRef = { current: false };
			const syncFailedRef = { current: false };
			usePaymentSetupHandler(
				onPaymentSetup,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				true,
				'',
				null,
				syncFailedRef
			);
			const result = await onPaymentSetupResultPromise;
			expect( result.type ).toBe( 'success' );
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

		// With a completion ref, a submission landing mid-(re)mount
		// waits for the element to settle instead of failing immediately.
		it( 'waits for an in-flight re-mount, then succeeds once the element completes', async () => {
			const hasLoadErrorRef = { current: false };
			const completeRef = { current: false };
			// The element finishes re-mounting while we wait.
			waitForPaymentElementCompletion.mockImplementation( () => {
				completeRef.current = true;
				return Promise.resolve( true );
			} );

			usePaymentSetupHandler(
				onPaymentSetup,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				false,
				'',
				completeRef
			);
			const result = await onPaymentSetupResultPromise;

			expect( waitForPaymentElementCompletion ).toHaveBeenCalledWith(
				completeRef
			);
			expect( result.type ).toBe( 'success' );
		} );

		it( 'returns the incomplete error when the element never completes within the wait', async () => {
			const hasLoadErrorRef = { current: false };
			const completeRef = { current: false };
			waitForPaymentElementCompletion.mockResolvedValue( false );

			usePaymentSetupHandler(
				onPaymentSetup,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				false,
				'',
				completeRef
			);
			const result = await onPaymentSetupResultPromise;

			expect( waitForPaymentElementCompletion ).toHaveBeenCalledWith(
				completeRef
			);
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
						wc_stripe_selected_upe_payment_type: '',
					},
				},
			} );
		} );

		it( 'forwards the actual selected payment method type so the server can set the order title', async () => {
			const hasLoadErrorRef = { current: false };
			usePaymentSetupHandler(
				onPaymentSetup,
				checkoutSessionId,
				null,
				hasLoadErrorRef,
				true,
				'ideal'
			);
			const result = await onPaymentSetupResultPromise;
			expect(
				result.meta.paymentMethodData
					.wc_stripe_selected_upe_payment_type
			).toBe( 'ideal' );
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

		const billing = {
			billingAddress: {
				first_name: 'John',
				last_name: 'Doe',
				country: 'US',
				address_1: '123 Main St',
				address_2: 'Apt 1',
				state: 'CA',
				city: 'Los Angeles',
				postcode: '90001',
			},
		};
		const shippingData = {
			shippingAddress: {
				first_name: 'Jane',
				last_name: 'Smith',
				country: 'US',
				address_1: '456 Oak Ave',
				address_2: '',
				state: 'NY',
				city: 'New York',
				postcode: '10001',
			},
		};

		let originalLocation;

		beforeEach( () => {
			document.body.innerHTML = '';
			originalLocation = window.location;
			delete window.location;
			window.location = { origin: 'https://example.com' };
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

		afterEach( () => {
			window.location = originalLocation;
		} );

		it( 'checkoutState.type is not success', async () => {
			const checkoutState = { type: 'error' };
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				true,
				false,
				shippingData
			);
			expect( await onCheckoutSuccessResultPromise ).toEqual( {
				type: 'error',
				message: 'Checkout is not ready for confirmation.',
			} );
		} );

		it( 'error confirming the session', async () => {
			const mockConfirm = jest.fn().mockResolvedValue( {
				type: 'error',
				error: { message: 'Test error.' },
			} );
			const checkoutState = {
				type: 'success',
				checkout: {
					email: 'test@example.com',
					confirm: mockConfirm,
				},
			};
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				true,
				false,
				shippingData
			);
			expect( await onCheckoutSuccessResultPromise ).toEqual( {
				type: 'error',
				message: 'Test error.',
			} );
			expect( mockConfirm ).toHaveBeenCalledWith( {
				billingAddress: {
					name: 'John Doe',
					address: {
						country: 'US',
						line1: '123 Main St',
						line2: 'Apt 1',
						state: 'CA',
						city: 'Los Angeles',
						postal_code: '90001',
					},
				},
				shippingAddress: {
					name: 'John Doe',
					address: {
						country: 'US',
						line1: '456 Oak Ave',
						state: 'NY',
						city: 'New York',
						postal_code: '10001',
					},
				},
				returnUrl: 'https://example.com/return-here',
				redirect: 'if_required',
				savePaymentMethod: false,
			} );
		} );

		it( 'confirms with an absolute returnUrl when the server returns a relative redirect', async () => {
			onCheckoutSuccess.mockImplementation( ( fn ) => {
				const onCheckoutProcessingData = {
					processingResponse: {
						paymentDetails: {
							redirect: '/order-received/123/?key=abc',
						},
					},
				};
				onCheckoutSuccessResultPromise = fn( onCheckoutProcessingData );
			} );

			const mockConfirm = jest.fn().mockResolvedValue( {
				type: 'success',
			} );
			const checkoutState = {
				type: 'success',
				checkout: { email: '', confirm: mockConfirm },
			};
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				false,
				false,
				shippingData
			);
			await onCheckoutSuccessResultPromise;

			expect( mockConfirm ).toHaveBeenCalledWith(
				expect.objectContaining( {
					returnUrl:
						'https://example.com/order-received/123/?key=abc',
				} )
			);
		} );

		it( 'success', async () => {
			const mockConfirm = jest.fn().mockResolvedValue( {
				type: 'success',
			} );
			const checkoutState = {
				type: 'success',
				checkout: {
					email: 'test@example.com',
					confirm: mockConfirm,
				},
			};
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				true,
				false,
				shippingData
			);
			expect( await onCheckoutSuccessResultPromise ).toEqual( {
				type: 'success',
			} );
		} );

		it( 'includes email from DOM when checkout.email is absent', async () => {
			const emailInput = document.createElement( 'input' );
			emailInput.id = 'email';
			emailInput.value = 'guest@example.com';
			document.body.appendChild( emailInput );

			const mockConfirm = jest.fn().mockResolvedValue( {
				type: 'success',
			} );
			const checkoutState = {
				type: 'success',
				checkout: {
					email: '',
					confirm: mockConfirm,
				},
			};
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				false,
				false,
				shippingData
			);
			await onCheckoutSuccessResultPromise;

			expect( mockConfirm ).toHaveBeenCalledWith(
				expect.objectContaining( { email: 'guest@example.com' } )
			);

			document.body.removeChild( emailInput );
		} );

		it( 'omits email when checkout.email is present', async () => {
			const mockConfirm = jest.fn().mockResolvedValue( {
				type: 'success',
			} );
			const checkoutState = {
				type: 'success',
				checkout: {
					email: 'loggedin@example.com',
					confirm: mockConfirm,
				},
			};
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				true,
				false,
				shippingData
			);
			await onCheckoutSuccessResultPromise;

			expect( mockConfirm ).toHaveBeenCalledWith(
				expect.not.objectContaining( { email: expect.anything() } )
			);
		} );

		it( 'includes phone from billing-phone DOM element', async () => {
			const phoneInput = document.createElement( 'input' );
			phoneInput.id = 'billing-phone';
			phoneInput.value = '555-1234';
			document.body.appendChild( phoneInput );

			const mockConfirm = jest.fn().mockResolvedValue( {
				type: 'success',
			} );
			const checkoutState = {
				type: 'success',
				checkout: {
					email: 'test@example.com',
					confirm: mockConfirm,
				},
			};
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				true,
				true,
				shippingData
			);
			await onCheckoutSuccessResultPromise;

			expect( mockConfirm ).toHaveBeenCalledWith(
				expect.objectContaining( { phoneNumber: '555-1234' } )
			);

			document.body.removeChild( phoneInput );
		} );

		it( 'falls back to shipping-phone when billing-phone is absent', async () => {
			const phoneInput = document.createElement( 'input' );
			phoneInput.id = 'shipping-phone';
			phoneInput.value = '555-5678';
			document.body.appendChild( phoneInput );

			const mockConfirm = jest.fn().mockResolvedValue( {
				type: 'success',
			} );
			const checkoutState = {
				type: 'success',
				checkout: {
					email: 'test@example.com',
					confirm: mockConfirm,
				},
			};
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				true,
				true,
				shippingData
			);
			await onCheckoutSuccessResultPromise;

			expect( mockConfirm ).toHaveBeenCalledWith(
				expect.objectContaining( { phoneNumber: '555-5678' } )
			);

			document.body.removeChild( phoneInput );
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
				checkout: { email: '', confirm },
			};
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				true,
				false,
				shippingData
			);
			await onCheckoutSuccessResultPromise;
			expect( confirm ).toHaveBeenCalledWith(
				expect.objectContaining( {
					returnUrl: 'https://example.com/return-here',
					redirect: 'if_required',
					savePaymentMethod: true,
				} )
			);
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

	describe( 'useCheckoutSessionTotalsSync hook', () => {
		afterEach( () => {
			useEffect.mockImplementation( ( fn ) => fn() );
		} );

		it( 'does not notify Stripe.js for the first embedded revision', () => {
			const checkoutState = {
				type: 'success',
				checkout: {
					id: 'cs_test',
					runServerUpdate: jest.fn( async ( fn ) => {
						await fn();
						return { type: 'success' };
					} ),
				},
			};

			renderHook( () =>
				useCheckoutSessionTotalsSync( 'cs_test', checkoutState, null, {
					revision: 1,
					status: 'success',
				} )
			);

			expect(
				checkoutState.checkout.runServerUpdate
			).not.toHaveBeenCalled();
		} );

		it( 'notifies Stripe.js when the Store API embeds a newer revision', async () => {
			let sessionData = { revision: 1, status: 'success' };
			const checkoutState = {
				type: 'success',
				checkout: {
					id: 'cs_test',
					runServerUpdate: jest.fn( async ( fn ) => {
						await fn();
						return { type: 'success' };
					} ),
				},
			};

			const { rerender } = renderHook( () =>
				useCheckoutSessionTotalsSync(
					'cs_test',
					checkoutState,
					null,
					sessionData
				)
			);

			sessionData = { revision: 2, status: 'success' };
			rerender();

			await waitFor( () => {
				expect(
					checkoutState.checkout.runServerUpdate
				).toHaveBeenCalled();
			} );
		} );

		it( 'flags the ref when the embedded Store API sync failed', async () => {
			const createErrorNotice = dispatch().createErrorNotice;
			createErrorNotice.mockClear();
			const syncFailedRef = { current: false };
			let sessionData = { revision: 1, status: 'success' };
			const checkoutState = {
				type: 'success',
				checkout: {
					id: 'cs_test',
					runServerUpdate: jest.fn(),
				},
			};

			const { rerender } = renderHook( () =>
				useCheckoutSessionTotalsSync(
					'cs_test',
					checkoutState,
					syncFailedRef,
					sessionData
				)
			);

			sessionData = { revision: 1, status: 'error' };
			rerender();

			await waitFor( () => {
				expect( syncFailedRef.current ).toBe( true );
			} );
			expect( createErrorNotice ).toHaveBeenCalledWith(
				STALE_TOTAL_MESSAGE,
				{
					id: 'wc-stripe-stale-checkout-total',
					context: 'wc/checkout/payments',
				}
			);
			expect(
				checkoutState.checkout.runServerUpdate
			).not.toHaveBeenCalled();
		} );

		it( 'reports a sync failure when the first embedded session state has an error', async () => {
			const createErrorNotice = dispatch().createErrorNotice;
			createErrorNotice.mockClear();
			const syncFailedRef = { current: false };
			const checkoutState = {
				type: 'success',
				checkout: {
					id: 'cs_test',
					runServerUpdate: jest.fn(),
				},
			};

			renderHook( () =>
				useCheckoutSessionTotalsSync(
					'cs_test',
					checkoutState,
					syncFailedRef,
					{ revision: 1, status: 'error' }
				)
			);

			await waitFor( () => {
				expect( syncFailedRef.current ).toBe( true );
			} );
			expect( createErrorNotice ).toHaveBeenCalledWith(
				STALE_TOTAL_MESSAGE,
				{
					id: 'wc-stripe-stale-checkout-total',
					context: 'wc/checkout/payments',
				}
			);
			expect(
				checkoutState.checkout.runServerUpdate
			).not.toHaveBeenCalled();
		} );

		it( 'reports a sync failure for a failed initial state when Checkout is not ready yet', async () => {
			const createErrorNotice = dispatch().createErrorNotice;
			createErrorNotice.mockClear();
			const syncFailedRef = { current: false };

			renderHook( () =>
				useCheckoutSessionTotalsSync(
					'cs_test',
					{ type: 'loading' },
					syncFailedRef,
					{ revision: 1, status: 'error' }
				)
			);

			await waitFor( () => {
				expect( syncFailedRef.current ).toBe( true );
			} );
			expect( createErrorNotice ).toHaveBeenCalledWith(
				STALE_TOTAL_MESSAGE,
				{
					id: 'wc-stripe-stale-checkout-total',
					context: 'wc/checkout/payments',
				}
			);
		} );

		it( 'runs the server update for a revision that landed before Checkout was ready', async () => {
			const runServerUpdate = jest.fn( async ( fn ) => {
				await fn();
				return { type: 'success' };
			} );
			let sessionData = { revision: 1, status: 'success' };
			let checkoutState = { type: 'loading' };

			const { rerender } = renderHook( () =>
				useCheckoutSessionTotalsSync(
					'cs_test',
					checkoutState,
					null,
					sessionData
				)
			);

			// A newer revision lands while the Checkout instance is still initializing.
			sessionData = { revision: 2, status: 'success' };
			rerender();

			expect( runServerUpdate ).not.toHaveBeenCalled();

			// Once Checkout is ready the revision must still be treated as pending.
			checkoutState = {
				type: 'success',
				checkout: { id: 'cs_test', runServerUpdate },
			};
			rerender();

			await waitFor( () => {
				expect( runServerUpdate ).toHaveBeenCalled();
			} );
		} );

		it( 'clears the sync failure flag after a newer revision is received', async () => {
			const syncFailedRef = { current: true };
			let sessionData = { revision: 1, status: 'error' };
			const checkoutState = {
				type: 'success',
				checkout: {
					id: 'cs_test',
					runServerUpdate: jest.fn( async ( fn ) => {
						await fn();
						return { type: 'success' };
					} ),
				},
			};

			const { rerender } = renderHook( () =>
				useCheckoutSessionTotalsSync(
					'cs_test',
					checkoutState,
					syncFailedRef,
					sessionData
				)
			);

			sessionData = { revision: 2, status: 'success' };
			rerender();

			await waitFor( () => {
				expect( syncFailedRef.current ).toBe( false );
			} );

			// The notice a prior failed resync showed must be retracted, not left stale.
			expect( dispatch().removeNotice ).toHaveBeenCalledWith(
				'wc-stripe-stale-checkout-total',
				'wc/checkout/payments'
			);
		} );

		// The jQuery mock returns a fresh chain per call, so block/unblock
		// invocations are summed across every chain it handed out.
		const countJQueryCalls = ( method ) =>
			jQuery.mock.results.reduce(
				( count, result ) =>
					count +
					( result.value?.[ method ]?.mock?.calls?.length ?? 0 ),
				0
			);

		// Allow the cancellation tests that need real effects to get that behaviour.
		const useRealEffects = () => {
			const { useEffect: actualUseEffect } =
				jest.requireActual( '@wordpress/element' );
			useEffect.mockImplementation( actualUseEffect );
		};

		it( 'retries a resync cancelled by a checkout state change and lifts the UI block', async () => {
			useRealEffects();
			jQuery.mockClear();
			const createErrorNotice = dispatch().createErrorNotice;
			createErrorNotice.mockClear();

			const resolvers = [];
			const runServerUpdate = jest.fn(
				() => new Promise( ( resolve ) => resolvers.push( resolve ) )
			);
			const syncFailedRef = { current: false };
			let sessionData = { revision: 1, status: 'success' };
			let checkoutState = {
				type: 'success',
				checkout: { id: 'cs_test', runServerUpdate },
			};

			const { rerender } = renderHook( () =>
				useCheckoutSessionTotalsSync(
					'cs_test',
					checkoutState,
					syncFailedRef,
					sessionData
				)
			);

			expect( countJQueryCalls( 'block' ) ).toBe( 0 );
			expect( countJQueryCalls( 'unblock' ) ).toBe( 0 );

			// A newer revision starts a resync that blocks the payment UI.
			sessionData = { revision: 2, status: 'success' };
			rerender();
			await waitFor( () => {
				expect( runServerUpdate ).toHaveBeenCalledTimes( 1 );
			} );
			expect( countJQueryCalls( 'block' ) ).toBe( 1 );
			expect( countJQueryCalls( 'unblock' ) ).toBe( 0 );

			// Checkout state changes while the resync is in flight.
			checkoutState = { type: 'loading' };
			rerender();

			// The cancelled run must unblock the UI immediately and must not
			// show a failure notice to the shopper.
			expect( countJQueryCalls( 'block' ) ).toBe( 1 );
			expect( countJQueryCalls( 'unblock' ) ).toBe( 1 );
			expect( createErrorNotice ).not.toHaveBeenCalled();

			// Once checkout recovers, the pending revision must be retried.
			checkoutState = {
				type: 'success',
				checkout: { id: 'cs_test', runServerUpdate },
			};
			rerender();
			await waitFor( () => {
				expect( runServerUpdate ).toHaveBeenCalledTimes( 2 );
			} );

			resolvers[ 1 ]( { type: 'success' } );
			await waitFor( () => {
				expect( countJQueryCalls( 'block' ) ).toBe( 2 );
			} );
			expect( countJQueryCalls( 'unblock' ) ).toBe( 2 );
			expect( syncFailedRef.current ).toBe( false );
		} );

		it( 'lifts the UI block when unmounted during an in-flight resync', async () => {
			useRealEffects();
			jQuery.mockClear();

			const runServerUpdate = jest.fn( () => new Promise( () => {} ) );
			let sessionData = { revision: 1, status: 'success' };
			const checkoutState = {
				type: 'success',
				checkout: { id: 'cs_test', runServerUpdate },
			};

			const { rerender, unmount } = renderHook( () =>
				useCheckoutSessionTotalsSync(
					'cs_test',
					checkoutState,
					null,
					sessionData
				)
			);

			expect( countJQueryCalls( 'block' ) ).toBe( 0 );
			expect( countJQueryCalls( 'unblock' ) ).toBe( 0 );

			sessionData = { revision: 2, status: 'success' };
			rerender();
			await waitFor( () => {
				expect( runServerUpdate ).toHaveBeenCalledTimes( 1 );
			} );
			expect( countJQueryCalls( 'block' ) ).toBe( 1 );
			expect( countJQueryCalls( 'unblock' ) ).toBe( 0 );

			unmount();
			expect( countJQueryCalls( 'unblock' ) ).toBe( 1 );
			expect( countJQueryCalls( 'block' ) ).toBe( 1 );
		} );
	} );
} );
