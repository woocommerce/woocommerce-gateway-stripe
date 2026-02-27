import { act, renderHook } from '@testing-library/react';
import { useExpressCheckout } from '../hooks';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

jest.mock( '@stripe/react-stripe-js', () => ( {
	useStripe: jest.fn( () => ( { confirmPayment: jest.fn() } ) ),
	useElements: jest.fn( () => ( { submit: jest.fn() } ) ),
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
} );
