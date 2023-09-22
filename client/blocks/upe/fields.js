import { useState, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	Elements,
	useStripe,
	useElements,
	PaymentElement,
} from '@stripe/react-stripe-js';
import { useMemo } from 'react';
import { getFontRulesFromPage, getAppearance } from '../../styles/upe';
import { confirmUpePayment } from './confirm-upe-payment';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import {
	PAYMENT_METHOD_NAME,
	WC_STORE_CART,
} from 'wcstripe/blocks/credit-card/constants';
import enableStripeLinkPaymentMethod from 'wcstripe/stripe-link';
import './styles.scss';
import {
	getStorageWithExpiration,
	setStorageWithExpiration,
	storageKeys,
} from 'wcstripe/stripe-utils';

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

const UPEField = ( {
	api,
	activePaymentMethod,
	billing: { billingData },
	eventRegistration: {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithSuccess,
	},
	emitResponse,
	paymentIntentId,
	errorMessage,
	shouldSavePayment,
} ) => {
	const stripe = useStripe();
	const elements = useElements();

	const [ selectedUpePaymentType, setSelectedUpePaymentType ] = useState(
		''
	);
	const [ isUpeComplete, setIsUpeComplete ] = useState( false );

	const paymentMethodsConfig = getBlocksConfiguration()?.paymentMethodsConfig;

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

						return confirmUpePayment(
							api,
							paymentDetails.redirect_url,
							paymentDetails.payment_needed,
							elements,
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
		wallets: {
			applePay: 'never',
			googlePay: 'never',
		},
	};

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
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( null );
	const appearance = useMemo( () => {
		const themeName = getBlocksConfiguration()?.theme_name;
		const storageKey = `${ storageKeys.WC_BLOCKS_UPE_APPEARANCE }_${ themeName }`;
		let newAppearance = getStorageWithExpiration( storageKey );

		if ( ! newAppearance ) {
			newAppearance = getAppearance( true );
			const oneDayDuration = 24 * 60 * 60 * 1000;
			setStorageWithExpiration(
				storageKey,
				newAppearance,
				oneDayDuration
			);
		}

		return newAppearance;
	}, [] );

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
	}, [ paymentIntentId, hasRequestedIntent, api, errorMessage, appearance ] );

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

	const options = {
		clientSecret,
		appearance,
		fonts: getFontRulesFromPage(),
	};

	return (
		<Elements stripe={ api.getStripe() } options={ options }>
			<UPEField
				api={ api }
				paymentIntentId={ paymentIntentId }
				errorMessage={ errorMessage }
				{ ...props }
			/>
		</Elements>
	);
};
