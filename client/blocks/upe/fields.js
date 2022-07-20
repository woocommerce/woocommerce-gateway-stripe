import { useState, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	Elements,
	ElementsConsumer,
	PaymentElement,
} from '@stripe/react-stripe-js';
import { getAppearance } from '../../styles/upe';
import { confirmUpePayment } from './confirm-upe-payment';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import {
	PAYMENT_METHOD_NAME,
	WC_STORE_CART,
} from 'wcstripe/blocks/credit-card/constants';
import enableStripeLinkPaymentMethod from 'wcstripe/stripe-link';
import './styles.scss';

const useCustomerData = () => {
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

const UPEField = ( {
	api,
	activePaymentMethod,
	billing: { billingData },
	elements,
	emitResponse,
	eventRegistration: {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithSuccess,
	},
	shouldSavePayment,
	stripe,
} ) => {
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ selectedUpePaymentType, setSelectedUpePaymentType ] = useState(
		''
	);
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );
	const [ isUpeComplete, setIsUpeComplete ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( null );

	const paymentMethodsConfig = getBlocksConfiguration()?.paymentMethodsConfig;

	useEffect( () => {
		if ( paymentIntentId || hasRequestedIntent ) {
			return;
		}

		async function createIntent() {
			try {
				const paymentNeeded = getBlocksConfiguration()?.isPaymentNeeded;
				const response = paymentNeeded
					? await api.createIntent(
							getBlocksConfiguration()?.orderId
					  )
					: await api.initSetupIntent();
				setPaymentIntentId( response.id );
				setClientSecret( response.client_secret );
			} catch ( error ) {
				setErrorMessage(
					error?.message ??
						__(
							'There was an error loading the payment gateway',
							'woocommerce-gateway-stripe'
						)
				);
			}
		}

		setHasRequestedIntent( true );
		createIntent();
	}, [ paymentIntentId, hasRequestedIntent, api, errorMessage ] );

	const customerData = useCustomerData();

	useEffect( () => {
		if (
			paymentMethodsConfig.link !== undefined &&
			paymentMethodsConfig.card !== undefined
		) {
			const shippingAddressFields = {
				line1: 'shipping-address_1',
				line2: 'shipping-address_2',
				city: 'shipping-city',
				state: 'components-form-token-input-1',
				postal_code: 'shipping-postcode',
				country: 'components-form-token-input-0',
			};
			const billingAddressFields = {
				line1: 'billing-address_1',
				line2: 'billing-address_2',
				city: 'billing-city',
				state: 'components-form-token-input-3',
				postal_code: 'billing-postcode',
				country: 'components-form-token-input-2',
			};

			const appearance = getAppearance();
			elements.update( { appearance } );

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
				complete_shipping:
					document.getElementById( 'shipping-address_1' ) !== null,
				shipping_fields: shippingAddressFields,
				billing_fields: billingAddressFields,
				complete_billing:
					document.getElementById( 'billing-address_1' ) !== null,
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ elements ] );

	useEffect(
		() =>
			onPaymentProcessing( () => {
				if ( activePaymentMethod !== 'stripe' ) {
					return;
				}

				if ( ! isUpeComplete ) {
					return {
						type: 'error',
						message: __(
							'Your payment information is incomplete.',
							'woocommerce-gateway-stripe'
						),
					};
				}

				if ( errorMessage ) {
					return {
						type: 'error',
						message: errorMessage,
					};
				}

				if (
					shouldSavePayment &&
					! paymentMethodsConfig[ selectedUpePaymentType ].isReusable
				) {
					return {
						type: 'error',
						message: __(
							'This payment method can not be saved for future use.',
							'woocommerce-gateway-stripe'
						),
					};
				}

				return {
					type: 'success',
					meta: {
						paymentMethodData: {
							paymentMethod: PAYMENT_METHOD_NAME,
							wc_payment_intent_id: paymentIntentId,
							wc_stripe_selected_upe_payment_type: selectedUpePaymentType,
						},
					},
				};
			} ),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[
			activePaymentMethod,
			isUpeComplete,
			shouldSavePayment,
			selectedUpePaymentType,
		]
	);

	useEffect(
		() =>
			onCheckoutAfterProcessingWithSuccess(
				( { orderId, processingResponse: { paymentDetails } } ) => {
					async function updateIntent() {
						await api.updateIntent(
							paymentIntentId,
							orderId,
							shouldSavePayment ? 'yes' : 'no',
							selectedUpePaymentType
						);

						const paymentElement = elements.getElement(
							PaymentElement
						);

						return confirmUpePayment(
							api,
							paymentDetails.redirect_url,
							paymentDetails.payment_needed,
							paymentElement,
							billingData,
							emitResponse
						);
					}

					return updateIntent();
				}
			),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[
			api,
			elements,
			paymentIntentId,
			selectedUpePaymentType,
			billingData,
			shouldSavePayment,
			stripe,
		]
	);

	const enabledBillingFields = getBlocksConfiguration().enabledBillingFields;
	const elementOptions = {
		clientSecret,
		business: { name: getBlocksConfiguration()?.accountDescriptor },
		fields: {
			billingDetails: {
				name:
					enabledBillingFields.includes( 'billing_first_name' ) ||
					enabledBillingFields.includes( 'billing_last_name' )
						? 'never'
						: 'auto',
				email: enabledBillingFields.includes( 'billing_email' )
					? 'never'
					: 'auto',
				phone: enabledBillingFields.includes( 'billing_phone' )
					? 'never'
					: 'auto',
				address: {
					country: enabledBillingFields.includes( 'billing_country' )
						? 'never'
						: 'auto',
					line1: enabledBillingFields.includes( 'billing_address_1' )
						? 'never'
						: 'auto',
					line2: enabledBillingFields.includes( 'billing_address_2' )
						? 'never'
						: 'auto',
					city: enabledBillingFields.includes( 'billing_city' )
						? 'never'
						: 'auto',
					state: enabledBillingFields.includes( 'billing_state' )
						? 'never'
						: 'auto',
					postalCode: enabledBillingFields.includes(
						'billing_postcode'
					)
						? 'never'
						: 'auto',
				},
			},
		},
	};

	if ( ! clientSecret ) {
		if ( errorMessage ) {
			return (
				<div className="woocommerce-error">
					<div className="components-notice__content">
						{ errorMessage }
					</div>
				</div>
			);
		}

		return null;
	}

	return (
		<div className="wc-block-gateway-container">
			<PaymentElement
				options={ elementOptions }
				onChange={ ( event ) => {
					setIsUpeComplete( event.complete );
					setSelectedUpePaymentType( event.value.type );
				} }
			/>
		</div>
	);
};

export const UPEPaymentForm = ( { api, ...props } ) => {
	return (
		<Elements stripe={ api.getStripe() }>
			<ElementsConsumer>
				{ ( { stripe, elements } ) => (
					<UPEField
						api={ api }
						elements={ elements }
						stripe={ stripe }
						{ ...props }
					/>
				) }
			</ElementsConsumer>
		</Elements>
	);
};
