import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import { getStripeServerData, getUPETerms } from '../../stripe-utils';
import { legacyHashchangeHandler } from './legacy-support';
import './style.scss';
import './deferred-intent.js';
import {
	maybeShowCashAppLimitNotice,
	removeCashAppLimitNotice,
} from 'wcstripe/stripe-utils/cash-app-limit-notice-handler';
import {
	PAYMENT_METHOD_BOLETO,
	PAYMENT_METHOD_MULTIBANCO,
	PAYMENT_METHOD_OXXO,
} from 'wcstripe/stripe-utils/constants';

jQuery( function ( $ ) {
	const key = getStripeServerData()?.key;
	const isUPEEnabled = getStripeServerData()?.isUPEEnabled;
	if ( ! key ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	// Create an API object, which will be used throughout the checkout.
	const api = new WCStripeAPI(
		getStripeServerData(),
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);

	const elements = null;
	const upeElement = null;
	const paymentIntentId = null;
	const isUPEComplete = false;

	/**
	 * Block UI to indicate processing and avoid duplicate submission.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 */
	const blockUI = ( $form ) => {
		$form.addClass( 'processing' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			},
		} );
	};

	/**
	 * Show error notice at top of checkout form.
	 * Will try to use a translatable message using the message code if available
	 *
	 * @param {string} errorMessage
	 */
	const showError = ( errorMessage ) => {
		if (
			typeof errorMessage !== 'string' &&
			! ( errorMessage instanceof String )
		) {
			if (
				errorMessage.code &&
				getStripeServerData()[ errorMessage.code ]
			) {
				errorMessage = getStripeServerData()[ errorMessage.code ];
			} else {
				errorMessage = errorMessage.message;
			}
		}

		let messageWrapper = '';
		if ( errorMessage.includes( 'woocommerce-error' ) ) {
			messageWrapper = errorMessage;
		} else {
			messageWrapper =
				'<ul class="woocommerce-error" role="alert"><li>' +
				errorMessage +
				'</li></ul>';
		}
		const $container = $( '.woocommerce-notices-wrapper' ).first();

		if ( ! $container.length ) {
			return;
		}

		// Adapted from WooCommerce core @ ea9aa8c, assets/js/frontend/checkout.js#L514-L529
		$(
			'.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
		).remove();
		$container.prepend( messageWrapper );
		$( 'form.checkout' )
			.find( '.input-text, select, input:checkbox' )
			.trigger( 'validate' )
			.blur();

		$.scroll_to_notices( $container );
		$( document.body ).trigger( 'checkout_error' );
	};

	/**
	 * Converts form fields object into Stripe `billing_details` object.
	 *
	 * @param {Object} fields Object mapping checkout billing fields to values.
	 * @return {Object} Stripe formatted `billing_details` object.
	 */
	const getBillingDetails = ( fields ) => {
		return {
			name:
				`${ fields.billing_first_name } ${ fields.billing_last_name }`.trim() ||
				'-',
			email: fields.billing_email || '-',
			phone: fields.billing_phone || '-',
			address: {
				country: fields.billing_country || '-',
				line1: fields.billing_address_1 || '-',
				line2: fields.billing_address_2 || '-',
				city: fields.billing_city || '-',
				state: fields.billing_state || '-',
				postal_code: fields.billing_postcode || '-',
			},
		};
	};

	/**
	 * Checks if UPE form is filled out. Displays errors if not.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @return {boolean} false if incomplete.
	 */
	const checkUPEForm = async ( $form ) => {
		if ( ! upeElement ) {
			showError( 'Your payment information is incomplete.' );
			return false;
		}
		if ( ! isUPEComplete ) {
			// If UPE fields are not filled, confirm payment to trigger validation errors
			const { error } = await api.getStripe().confirmPayment( {
				elements,
				confirmParams: {
					return_url: '#',
				},
			} );
			$form.removeClass( 'processing' ).unblock();
			showError( error.message );
			return false;
		}
		return true;
	};

	/**
	 * Submits checkout form via AJAX to create order and uses custom
	 * redirect URL in AJAX response to request payment confirmation from UPE
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @return {boolean} A flag for the event handler.
	 */
	const handleUPECheckout = async ( $form ) => {
		const isUPEFormValid = await checkUPEForm( $form );
		if ( ! isUPEFormValid ) {
			return;
		}

		blockUI( $form );
		// Create object where keys are form field names and values are form field values
		const formFields = $form.serializeArray().reduce( ( obj, field ) => {
			obj[ field.name ] = field.value;
			return obj;
		}, {} );
		try {
			const response = await api.processCheckout(
				paymentIntentId,
				formFields
			);
			const redirectUrl = response.redirect_url;
			const upeConfig = {
				elements,
				confirmParams: {
					return_url: redirectUrl,
					payment_method_data: {
						billing_details: getBillingDetails( formFields ),
					},
				},
			};
			let error;
			if ( response.payment_needed ) {
				( { error } = await api
					.getStripe()
					.confirmPayment( upeConfig ) );
			} else {
				( { error } = await api.getStripe().confirmSetup( upeConfig ) );
			}

			if ( error ) {
				const upeType = formFields.wc_stripe_selected_upe_payment_type;

				if (
					upeType !== PAYMENT_METHOD_BOLETO &&
					upeType !== PAYMENT_METHOD_OXXO &&
					upeType !== PAYMENT_METHOD_MULTIBANCO
				) {
					await api.updateFailedOrder(
						paymentIntentId,
						response.order_id
					);
				}

				throw error;
			}
		} catch ( error ) {
			$form.removeClass( 'processing' ).unblock();
			showError( error );
		}
	};

	/**
	 * Displays the authentication modal to the user if needed.
	 */
	const maybeShowAuthenticationModal = () => {
		const paymentMethodId = $( '#wc-stripe-payment-method' ).val();

		const savePaymentMethod = $( '#wc-stripe-new-payment-method' ).is(
			':checked'
		);
		const confirmation = api.confirmIntent(
			window.location.href,
			savePaymentMethod ? paymentMethodId : null
		);

		// Boolean `true` means that there is nothing to confirm.
		if ( confirmation === true ) {
			return;
		}

		const { request, isOrderPage } = confirmation;

		if ( isOrderPage ) {
			blockUI( $( '#order_review' ) );
			$( '#payment' ).hide( 500 );
		}

		// Cleanup the URL.
		// https://stackoverflow.com/a/5298684
		// eslint-disable-next-line no-undef
		history.replaceState(
			'',
			document.title,
			window.location.pathname + window.location.search
		);

		request
			.then( ( redirectUrl ) => {
				window.location = redirectUrl;
			} )
			.catch( ( error ) => {
				$( 'form.checkout' ).removeClass( 'processing' ).unblock();
				$( '#order_review' ).removeClass( 'processing' ).unblock();
				$( '#payment' ).show( 500 );

				let errorMessage = error.message;

				// If this is a generic error, we probably don't want to display the error message to the user,
				// so display a generic message instead.
				if ( error instanceof Error ) {
					errorMessage = getStripeServerData()?.genericErrorMessage;
				}

				showError( errorMessage );
			} );
	};

	/**
	 * Checks if the customer is using a saved payment method.
	 *
	 * @return {boolean} Boolean indicating whether or not a saved payment method is being used.
	 */
	function isUsingSavedPaymentMethod() {
		return (
			$( '#wc-stripe-payment-token-new' ).length &&
			! $( '#wc-stripe-payment-token-new' ).is( ':checked' )
		);
	}

	// Handle the checkout form when WooCommerce Gateway Stripe is chosen.
	$( 'form.checkout' )
		.on( 'checkout_place_order_stripe', function () {
			if ( ! isUsingSavedPaymentMethod() ) {
				if ( isUPEEnabled && paymentIntentId ) {
					handleUPECheckout( $( this ) );
					return false;
				}
			}
		} )
		.on( 'change', '.wc_payment_methods', () => {
			// Check to see whether we should display the Cash App limit notice.
			if ( $( 'input#payment_method_stripe_cashapp' ).is( ':checked' ) ) {
				maybeShowCashAppLimitNotice(
					'.woocommerce-checkout-payment',
					Number( getStripeServerData()?.cartTotal )
				);
			} else {
				removeCashAppLimitNotice();
			}
		} );

	// Add terms parameter to UPE if save payment information checkbox is checked.
	// This shows required legal mandates when customer elects to save payment method during checkout.
	$( document ).on( 'change', '#wc-stripe-new-payment-method', () => {
		const value = $( '#wc-stripe-new-payment-method' ).is( ':checked' )
			? 'always'
			: 'never';
		if ( isUPEEnabled && upeElement ) {
			upeElement.update( {
				terms: getUPETerms( value ),
			} );
		}
	} );

	// On every page load, check to see whether we should display the authentication
	// modal and display it if it should be displayed.
	maybeShowAuthenticationModal();

	// Handle hash change - used when authenticating payment with SCA on checkout page.
	$( window ).on( 'hashchange', () => {
		if ( window.location.hash.startsWith( '#wc-stripe-confirm-' ) ) {
			maybeShowAuthenticationModal();
		} else if ( window.location.hash.startsWith( '#confirm-' ) ) {
			legacyHashchangeHandler( api, showError );
		}
	} );
} );
