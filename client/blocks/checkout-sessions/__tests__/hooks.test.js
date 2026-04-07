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
			useCheckoutSuccessHandler(
				checkoutState,
				onCheckoutSuccess,
				billing,
				true,
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
} );
