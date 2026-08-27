import { act, renderHook } from '@testing-library/react';
import { useExpressCheckout } from '../hooks';
import { onConfirmHandler } from 'wcstripe/express-checkout/event-handler';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

// Stable singletons, matching how react-stripe-js returns the same instances.
const mockStripe = { confirmPayment: jest.fn() };
const mockElements = { submit: jest.fn() };
jest.mock( '@stripe/react-stripe-js', () => ( {
	useStripe: jest.fn( () => mockStripe ),
	useElements: jest.fn( () => mockElements ),
} ) );

jest.mock( 'wcstripe/express-checkout/event-handler', () => ( {
	onAbortPaymentHandler: jest.fn(),
	onCancelHandler: jest.fn(),
	onClickHandler: jest.fn(),
	onCompletePaymentHandler: jest.fn(),
	onConfirmHandler: jest.fn(),
} ) );

jest.mock( 'wcstripe/express-checkout/utils', () => ( {
	displayExpressCheckoutNotice: jest.fn(),
	getExpressCheckoutButtonStyleSettings: jest.fn( () => ( {
		paymentMethods: {},
	} ) ),
	getExpressCheckoutData: jest.fn(),
	normalizeLineItems: jest.fn( ( displayItems ) => displayItems ),
} ) );

const setDefaultExpressCheckoutDataMock = () => {
	getExpressCheckoutData.mockImplementation( ( key ) => {
		if ( key === 'checkout' ) {
			return {
				currency_decimals: 2,
				needs_payer_phone: true,
			};
		}

		if ( key === 'taxes_based_on_billing' ) {
			return false;
		}

		return null;
	} );
};

describe( 'useExpressCheckout', () => {
	beforeEach( () => {
		setDefaultExpressCheckoutDataMock();
	} );

	it( 'transforms line items and shipping rates before resolving the click event', async () => {
		const onClick = jest.fn();
		const onClose = jest.fn();
		const setExpressPaymentError = jest.fn();

		const billing = {
			currency: { minorUnit: 0 },
			cartTotal: { value: 7500 },
			cartTotalItems: [ { name: 'Subtotal', amount: 7500 } ],
		};

		const shippingData = {
			needsShipping: true,
			shippingRates: [
				{
					shipping_rates: [
						{
							rate_id: 'flat_rate:1',
							price: '7500',
							name: 'Flat rate',
						},
					],
				},
			],
		};

		const { result } = renderHook( () =>
			useExpressCheckout( {
				api: {},
				billing,
				shippingData,
				onClick,
				onClose,
				setExpressPaymentError,
			} )
		);

		const event = {
			resolve: jest.fn(),
		};

		await act( async () => {
			await result.current.onButtonClick( event );
		} );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [ { name: 'Subtotal', amount: 750000 } ],
				shippingRates: [
					{
						id: 'flat_rate:1',
						amount: 750000,
						displayName: 'Flat rate',
					},
				],
				emailRequired: true,
				shippingAddressRequired: true,
				phoneNumberRequired: true,
			} )
		);
	} );

	it( 'passes ISK amounts through unchanged when minorUnit and currency_decimals are both 0', async () => {
		getExpressCheckoutData.mockImplementation( ( key ) => {
			if ( key === 'checkout' ) {
				return {
					currency_decimals: 0,
					needs_payer_phone: false,
				};
			}

			if ( key === 'taxes_based_on_billing' ) {
				return false;
			}

			return null;
		} );

		const onClick = jest.fn();
		const onClose = jest.fn();
		const setExpressPaymentError = jest.fn();
		const billing = {
			currency: { minorUnit: 0 },
			cartTotal: { value: 4500 },
			cartTotalItems: [ { name: 'Subtotal', amount: 4500 } ],
		};
		const shippingData = {
			needsShipping: true,
			shippingRates: [
				{
					shipping_rates: [
						{
							rate_id: 'flat_rate:1',
							price: '4500',
							name: 'Flat rate',
						},
					],
				},
			],
		};
		const { result } = renderHook( () =>
			useExpressCheckout( {
				api: {},
				billing,
				shippingData,
				onClick,
				onClose,
				setExpressPaymentError,
			} )
		);
		const event = {
			resolve: jest.fn(),
		};

		await act( async () => {
			await result.current.onButtonClick( event );
		} );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [ { name: 'Subtotal', amount: 4500 } ],
				shippingRates: [
					{
						id: 'flat_rate:1',
						amount: 4500,
						displayName: 'Flat rate',
					},
				],
				emailRequired: true,
				shippingAddressRequired: true,
				phoneNumberRequired: false,
			} )
		);
	} );

	it( 'omits line items when transformed total is less than the sum of line item amounts', async () => {
		const onClick = jest.fn();
		const onClose = jest.fn();
		const setExpressPaymentError = jest.fn();
		const billing = {
			currency: { minorUnit: 0 },
			cartTotal: { value: 74 },
			cartTotalItems: [ { name: 'Subtotal', amount: 75 } ],
		};
		const shippingData = {
			needsShipping: false,
			shippingRates: [],
		};
		const { result } = renderHook( () =>
			useExpressCheckout( {
				api: {},
				billing,
				shippingData,
				onClick,
				onClose,
				setExpressPaymentError,
			} )
		);
		const event = {
			resolve: jest.fn(),
		};

		await act( async () => {
			await result.current.onButtonClick( event );
		} );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [],
				emailRequired: true,
				shippingAddressRequired: false,
				phoneNumberRequired: true,
			} )
		);
	} );

	// An order-side failure still has to give the wallet sheet a terminal result,
	// otherwise it stays open and the shopper is left with nothing.
	it( 'fails the payment on the wallet sheet when the order errors', async () => {
		const setExpressPaymentError = jest.fn();

		const { result } = renderHook( () =>
			useExpressCheckout( {
				api: {},
				billing: {
					currency: { minorUnit: 2 },
					cartTotal: { value: 7500 },
					cartTotalItems: [],
				},
				shippingData: { needsShipping: false, shippingRates: [] },
				onClick: jest.fn(),
				onClose: jest.fn(),
				setExpressPaymentError,
			} )
		);

		const event = { paymentFailed: jest.fn() };
		await act( async () => {
			await result.current.onConfirm( event );
		} );

		const { abortPayment } = onConfirmHandler.mock.calls[ 0 ][ 0 ];
		abortPayment( event, 'Order creation error' );

		expect( event.paymentFailed ).toHaveBeenCalledWith( {
			reason: 'fail',
		} );
		expect( setExpressPaymentError ).toHaveBeenCalledWith(
			'Order creation error'
		);
	} );

	// Blocks passes fresh billing/shippingData refs each cart tick; memoised
	// outputs must stay stable so the Stripe element doesn't churn.
	it( 'keeps memoised outputs referentially stable across a cart-data re-render', () => {
		const onClick = jest.fn();
		const onClose = jest.fn();
		const setExpressPaymentError = jest.fn();
		const api = {};

		const makeProps = () => ( {
			api,
			billing: {
				currency: { minorUnit: 2, code: 'USD' },
				cartTotal: { value: 7500 },
				cartTotalItems: [ { name: 'Subtotal', amount: 7500 } ],
			},
			shippingData: { needsShipping: true, shippingRates: [] },
			onClick,
			onClose,
			setExpressPaymentError,
			expressPaymentMethod: 'applePay',
		} );

		const { result, rerender } = renderHook(
			( props ) => useExpressCheckout( props ),
			{ initialProps: makeProps() }
		);

		const first = result.current;

		// Re-render with brand-new billing/shippingData refs (same values).
		rerender( makeProps() );

		expect( result.current.buttonOptions ).toBe( first.buttonOptions );
		expect( result.current.onConfirm ).toBe( first.onConfirm );
		expect( result.current.onCancel ).toBe( first.onCancel );
	} );

	it( 'rebuilds buttonOptions when the express payment method changes', () => {
		const baseProps = {
			api: {},
			billing: {
				currency: { minorUnit: 2, code: 'USD' },
				cartTotal: { value: 7500 },
				cartTotalItems: [],
			},
			shippingData: { needsShipping: false, shippingRates: [] },
			onClick: jest.fn(),
			onClose: jest.fn(),
			setExpressPaymentError: jest.fn(),
			expressPaymentMethod: 'applePay',
		};

		const { result, rerender } = renderHook(
			( props ) => useExpressCheckout( props ),
			{ initialProps: baseProps }
		);

		const first = result.current.buttonOptions;

		rerender( { ...baseProps, expressPaymentMethod: 'googlePay' } );

		expect( result.current.buttonOptions ).not.toBe( first );
	} );
} );
