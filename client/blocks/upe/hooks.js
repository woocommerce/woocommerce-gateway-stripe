import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import confirmCardPayment from './confirm-card-payment.js';
import enableStripeLinkPaymentMethod from 'wcstripe/stripe-link';
import { WC_STORE_CART } from 'wcstripe/blocks/credit-card/constants';
import { isLinkEnabled } from 'wcstripe/stripe-utils';

/**
 * Handles the Block Checkout onCheckoutSuccess event.
 *
 * Confirms the payment intent which was created on server and is now ready to be confirmed. The intent ID is passed in the paymentDetails object via the
 * redirect arg which will be in the following format: #wc-stripe-confirm-pi/si:{order_id}:{client_secret}:{nonce}
 *
 * @param {*} api               The api object.
 * @param {*} stripe            The Stripe object.
 * @param {*} elements          The Stripe elements object.
 * @param {*} onCheckoutSuccess The onCheckoutSuccess event.
 * @param {*} emitResponse      Various helpers for usage with observer.
 * @param {*} shouldSavePayment Whether or not to save the payment method.
 */
export const usePaymentCompleteHandler = (
	api,
	stripe,
	elements,
	onCheckoutSuccess,
	emitResponse,
	shouldSavePayment
) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	useEffect(
		() =>
			onCheckoutSuccess( ( { processingResponse: { paymentDetails } } ) =>
				confirmCardPayment(
					api,
					paymentDetails,
					emitResponse,
					shouldSavePayment
				)
			),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ elements, stripe, api, shouldSavePayment ]
	);
};

/**
 * Handles the Block Checkout onCheckoutFail event.
 *
 * Displays the error message returned from server in the paymentDetails object in the PAYMENTS notice context container.
 *
 * @param {*} api            The api object.
 * @param {*} stripe         The Stripe object.
 * @param {*} elements       The Stripe elements object.
 * @param {*} onCheckoutFail The onCheckoutFail event.
 * @param {*} emitResponse   Various helpers for usage with observer.
 */
export const usePaymentFailHandler = (
	api,
	stripe,
	elements,
	onCheckoutFail,
	emitResponse
) => {
	useEffect(
		() =>
			onCheckoutFail( ( { processingResponse: { paymentDetails } } ) => {
				return {
					type: 'failure',
					message: paymentDetails.errorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			} ),
		[
			elements,
			stripe,
			api,
			onCheckoutFail,
			emitResponse.noticeContexts.PAYMENTS,
		]
	);
};

/**
 * Handles rendering the Block Checkout Stripe Link payment method.
 *
 * @param {*} api                  The api object.
 * @param {*} elements             The Stripe elements object.
 * @param {*} paymentMethodsConfig The payment methods config object. Used to determine if Stripe Link is enabled.
 */
export const useStripeLink = ( api, elements, paymentMethodsConfig ) => {
	const customerData = useCustomerData();
	useEffect( () => {
		if ( isLinkEnabled( paymentMethodsConfig ) ) {
			const shippingAddressFields = {
				line1: 'shipping-address_1',
				line2: 'shipping-address_2',
				city: 'shipping-city',
				state: 'components-form-token-input-1',
				postal_code: 'shipping-postcode',
				country: 'components-form-token-input-0',
				first_name: 'shipping-first_name',
				last_name: 'shipping-last_name',
			};
			const billingAddressFields = {
				line1: 'billing-address_1',
				line2: 'billing-address_2',
				city: 'billing-city',
				state: 'components-form-token-input-3',
				postal_code: 'billing-postcode',
				country: 'components-form-token-input-2',
				first_name: 'billing-first_name',
				last_name: 'billing-last_name',
			};

			enableStripeLinkPaymentMethod( {
				api,
				elements,
				emailId: 'email',
				fill_field_method: ( address, nodeId, key ) => {
					const setAddress =
						shippingAddressFields[ key ] === nodeId
							? customerData.setShippingAddress
							: customerData.setBillingAddress;
					const customerAddress =
						shippingAddressFields[ key ] === nodeId
							? customerData.shippingAddress
							: customerData.billingAddress;

					if ( undefined === customerAddress ) {
						return;
					}

					if ( address.address[ key ] === null ) {
						address.address[ key ] = '';
					}

					if ( key === 'line1' ) {
						customerAddress.address_1 = address.address[ key ];
					} else if ( key === 'line2' ) {
						customerAddress.address_2 = address.address[ key ];
					} else if ( key === 'postal_code' ) {
						customerAddress.postcode = address.address[ key ];
					} else {
						customerAddress[ key ] = address.address[ key ];
					}

					if ( undefined !== customerData.billingAddress ) {
						customerAddress.email = getEmail();
					}

					setAddress( customerAddress );

					function getEmail() {
						return document.getElementById( 'email' ).value;
					}

					customerData.billingAddress.email = getEmail();
					customerData.setBillingAddress(
						customerData.billingAddress
					);
				},
				show_button: ( linkAutofill ) => {
					jQuery( '#email' )
						.parent()
						.append(
							'<button class="stripe-gateway-stripelink-modal-trigger"></button>'
						);
					if ( jQuery( '#email' ).val() !== '' ) {
						jQuery(
							'.stripe-gateway-stripelink-modal-trigger'
						).show();

						const linkButtonTop =
							jQuery( '#email' ).position().top +
							( jQuery( '#email' ).outerHeight() - 40 ) / 2;
						jQuery(
							'.stripe-gateway-stripelink-modal-trigger'
						).show();
						jQuery(
							'.stripe-gateway-stripelink-modal-trigger'
						).css( 'top', linkButtonTop + 'px' );
					}

					//Handle StripeLink button click.
					jQuery( '.stripe-gateway-stripelink-modal-trigger' ).on(
						'click',
						( event ) => {
							event.preventDefault();
							// Trigger modal.
							linkAutofill.launch( {
								email: jQuery( '#email' ).val(),
							} );
						}
					);
				},
				complete_shipping: () => {
					return (
						document.getElementById( 'shipping-address_1' ) !== null
					);
				},
				shipping_fields: shippingAddressFields,
				billing_fields: billingAddressFields,
				complete_billing: () => {
					return (
						document.getElementById( 'billing-address_1' ) !== null
					);
				},
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ elements ] );
};

/**
 * Returns the customer data and setters for the customer data.
 *
 * @return {Object} An object containing the customer data.
 */
export const useCustomerData = () => {
	const { customerData, isInitialized } = useSelect( ( select ) => {
		const store = select( WC_STORE_CART );
		return {
			customerData: store.getCustomerData(),
			isInitialized: store.hasFinishedResolution( 'getCartData' ),
		};
	} );
	const {
		setShippingAddress,
		setBillingAddress,
		setBillingData,
	} = useDispatch( WC_STORE_CART );

	let customerBillingAddress = customerData.billingData;
	let setCustomerBillingAddress = setBillingData;

	//added for backwards compatibility -> billingData was renamed to billingAddress
	if ( customerData.billingData === undefined ) {
		customerBillingAddress = customerData.billingAddress;
		setCustomerBillingAddress = setBillingAddress;
	}

	return {
		isInitialized,
		billingAddress: customerBillingAddress,
		shippingAddress: customerData.shippingAddress,
		setBillingAddress: setCustomerBillingAddress,
		setShippingAddress,
	};
};
