import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import { getStripeServerData, getUPETerms } from '../../stripe-utils';
import { getFontRulesFromPage, getAppearance } from '../../styles/upe';
import { legacyHashchangeHandler } from './legacy-support';
import './style.scss';

jQuery( function ( $ ) {
	const key = getStripeServerData()?.key;
	const isUPEEnabled = getStripeServerData()?.isUPEEnabled;
	const paymentMethodsConfig = getStripeServerData()?.paymentMethodsConfig;
	const enabledBillingFields = getStripeServerData()?.enabledBillingFields;

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

	// Object to add hidden elements to compute focus and invalid states for UPE.
	const hiddenElementsForUPE = {
		getHiddenContainer() {
			const hiddenDiv = document.createElement( 'div' );
			hiddenDiv.setAttribute( 'id', 'wc-stripe-hidden-div' );
			hiddenDiv.style.border = 0;
			hiddenDiv.style.clip = 'rect(0 0 0 0)';
			hiddenDiv.style.height = '1px';
			hiddenDiv.style.margin = '-1px';
			hiddenDiv.style.overflow = 'hidden';
			hiddenDiv.style.padding = '0';
			hiddenDiv.style.position = 'absolute';
			hiddenDiv.style.width = '1px';
			return hiddenDiv;
		},
		getHiddenInvalidRow() {
			const hiddenInvalidRow = document.createElement( 'p' );
			hiddenInvalidRow.classList.add(
				'form-row',
				'woocommerce-invalid',
				'woocommerce-invalid-required-field'
			);
			return hiddenInvalidRow;
		},
		appendHiddenClone( container, idToClone, hiddenCloneId ) {
			const hiddenInput = jQuery( idToClone )
				.clone()
				.prop( 'id', hiddenCloneId );
			container.appendChild( hiddenInput.get( 0 ) );
			return hiddenInput;
		},
		init() {
			if ( ! $( ' #billing_first_name' ).length ) {
				return;
			}
			const hiddenDiv = this.getHiddenContainer();

			// // Hidden focusable element.
			$( hiddenDiv ).insertAfter( '#billing_first_name' );
			this.appendHiddenClone(
				hiddenDiv,
				'#billing_first_name',
				'wc-stripe-hidden-input'
			);
			$( '#wc-stripe-hidden-input' ).trigger( 'focus' );

			// Hidden invalid element.
			const hiddenInvalidRow = this.getHiddenInvalidRow();
			this.appendHiddenClone(
				hiddenInvalidRow,
				'#billing_first_name',
				'wc-stripe-hidden-invalid-input'
			);
			hiddenDiv.appendChild( hiddenInvalidRow );

			// Remove transitions.
			$( '#wc-stripe-hidden-input' ).css( 'transition', 'none' );
		},
		cleanup() {
			$( '#wc-stripe-hidden-div' ).remove();
		},
	};

	const elements = api.getStripe().elements( {
		fonts: getFontRulesFromPage(),
	} );
	const sepaElementsOptions =
		getStripeServerData()?.sepaElementsOptions ?? {};
	const iban = elements.create( 'iban', sepaElementsOptions );

	let upeElement = null;
	let paymentIntentId = null;
	let isUPEComplete = false;
	const hiddenBillingFields = {
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
			postalCode: enabledBillingFields.includes( 'billing_postcode' )
				? 'never'
				: 'auto',
		},
	};
	const upeLoadingSelector = '#wc-stripe-upe-form';

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
	 * Unblock UI to remove overlay and loading icon
	 *
	 * @param {Object} $form The jQuery object for the form.
	 */
	const unblockUI = ( $form ) => {
		$form.removeClass( 'processing' ).unblock();
	};

	/**
	 * Checks whether SEPA IBAN element is present in the DOM and needs to be mounted
	 *
	 * @return {boolean} Whether IBAN needs to be mounted
	 */
	const doesIbanNeedToBeMounted = () => {
		return (
			$( '#stripe-iban-element' ).length &&
			! $( '#stripe-iban-element' ).children().length
		);
	};

	// Show error notice at top of checkout form.
	const showError = ( errorMessage ) => {
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

	// Show or hide save payment information checkbox
	const showNewPaymentMethodCheckbox = ( show = true ) => {
		if ( show ) {
			$( '.woocommerce-SavedPaymentMethods-saveNew' ).show();
		} else {
			$( '.woocommerce-SavedPaymentMethods-saveNew' ).hide();
			$( 'input#wc-stripe-new-payment-method' ).prop( 'checked', false );
			$( 'input#wc-stripe-new-payment-method' ).trigger( 'change' );
		}
	};

	// Set the selected UPE payment type field
	const setSelectedUPEPaymentType = ( paymentType ) => {
		$( '#wc_stripe_selected_upe_payment_type' ).val( paymentType );
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
	 * Mounts Stripe UPE element if feature is enabled.
	 *
	 * @param {boolean} isSetupIntent {Boolean} isSetupIntent Set to true if we are on My Account adding a payment method.
	 */
	const mountUPEElement = function ( isSetupIntent = false ) {
		blockUI( $( upeLoadingSelector ) );

		// Do not recreate UPE element unnecessarily.
		if ( upeElement ) {
			upeElement.unmount();
			upeElement.mount( '#wc-stripe-upe-element' );
			return;
		}

		// If paying from order, we need to create Payment Intent from order not cart.
		const isOrderPay = getStripeServerData()?.isOrderPay;
		const isCheckout = getStripeServerData()?.isCheckout;
		let orderId;
		if ( isOrderPay ) {
			orderId = getStripeServerData()?.orderId;
		}

		const intentAction = isSetupIntent
			? api.initSetupIntent()
			: api.createIntent( orderId );

		intentAction
			.then( ( response ) => {
				// I repeat, do NOT recreate UPE element unnecessarily.
				if ( upeElement || paymentIntentId ) {
					upeElement.unmount();
					upeElement.mount( '#wc-stripe-upe-element' );
					return;
				}

				const { client_secret: clientSecret, id: id } = response;
				paymentIntentId = id;

				let appearance = getStripeServerData()?.upeAppeareance;

				if ( ! appearance ) {
					hiddenElementsForUPE.init();
					appearance = getAppearance();
					hiddenElementsForUPE.cleanup();
					api.saveUPEAppearance( appearance );
				}

				const businessName = getStripeServerData()?.accountDescriptor;
				const upeSettings = {
					clientSecret,
					appearance,
					business: { name: businessName },
				};
				if ( isCheckout && ! isOrderPay ) {
					upeSettings.fields = {
						billingDetails: hiddenBillingFields,
					};
				}

				upeElement = elements.create( 'payment', upeSettings );
				upeElement.mount( '#wc-stripe-upe-element' );

				upeElement.on( 'ready', () => {
					unblockUI( $( upeLoadingSelector ) );
				} );
				upeElement.on( 'change', ( event ) => {
					const selectedUPEPaymentType = event.value.type;
					const isPaymentMethodReusable =
						paymentMethodsConfig[ selectedUPEPaymentType ]
							.isReusable;
					showNewPaymentMethodCheckbox( isPaymentMethodReusable );
					setSelectedUPEPaymentType( selectedUPEPaymentType );
					isUPEComplete = event.complete;
				} );
			} )
			.catch( ( error ) => {
				unblockUI( $( upeLoadingSelector ) );
				showError( error.message );
				const gatewayErrorMessage =
					'<div>An error was encountered when preparing the payment form. Please try again later.</div>';
				$( '.payment_box.payment_method_woocommerce_payments' ).html(
					gatewayErrorMessage
				);
			} );
	};

	// Only attempt to mount the card element once that section of the page has loaded. We can use the updated_checkout
	// event for this. This part of the page can also reload based on changes to checkout details, so we call unmount
	// first to ensure the card element is re-mounted correctly.
	$( document.body ).on( 'updated_checkout', () => {
		// If the card element selector doesn't exist, then do nothing (for example, when a 100% discount coupon is applied).
		// We also don't re-mount if already mounted in DOM.
		if (
			$( '#wc-stripe-upe-element' ).length &&
			! $( '#wc-stripe-upe-element' ).children().length &&
			isUPEEnabled
		) {
			const isSetupIntent = ! (
				getStripeServerData()?.isPaymentRequired ?? true
			);
			mountUPEElement( isSetupIntent );
		}

		if ( doesIbanNeedToBeMounted() ) {
			iban.mount( '#stripe-iban-element' );
		}
	} );

	if (
		$( 'form#add_payment_method' ).length ||
		$( 'form#order_review' ).length
	) {
		if (
			$( '#wc-stripe-upe-element' ).length &&
			! $( '#wc-stripe-upe-element' ).children().length &&
			isUPEEnabled &&
			! upeElement
		) {
			const isChangingPayment = getStripeServerData()?.isChangingPayment;

			// We use a setup intent if we are on the screens to add a new payment method or to change a subscription payment.
			const isSetupIntent =
				$( 'form#add_payment_method' ).length || isChangingPayment;

			if ( isChangingPayment && getStripeServerData()?.newTokenFormId ) {
				// Changing the method for a subscription takes two steps:
				// 1. Create the new payment method that will redirect back.
				// 2. Select the new payment method and resubmit the form to update the subscription.
				const token = getStripeServerData()?.newTokenFormId;
				$( token ).prop( 'selected', true ).trigger( 'click' );
				$( 'form#order_review' ).submit();
			}
			mountUPEElement( isSetupIntent );
		}

		if ( doesIbanNeedToBeMounted() ) {
			iban.mount( '#stripe-iban-element' );
		}
	}

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
				element: upeElement,
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
	 * Submits the confirmation of the intent to Stripe on Pay for Order page.
	 * Stripe redirects to Order Thank you page on sucess.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @return {boolean} A flag for the event handler.
	 */
	const handleUPEOrderPay = async ( $form ) => {
		const isUPEFormValid = await checkUPEForm( $( '#order_review' ) );
		if ( ! isUPEFormValid ) {
			return;
		}
		blockUI( $form );

		try {
			const isSavingPaymentMethod = $(
				'#wc-stripe-new-payment-method'
			).is( ':checked' );
			const savePaymentMethod = isSavingPaymentMethod ? 'yes' : 'no';

			const returnUrl =
				getStripeServerData()?.orderReturnURL +
				`&save_payment_method=${ savePaymentMethod }`;

			const orderId = getStripeServerData()?.orderId;

			// Update payment intent with level3 data, customer and maybe setup for future use.
			await api.updateIntent(
				paymentIntentId,
				orderId,
				savePaymentMethod,
				$( '#wc_stripe_selected_upe_payment_type' ).val()
			);

			const { error } = await api.getStripe().confirmPayment( {
				element: upeElement,
				confirmParams: {
					return_url: returnUrl,
				},
			} );
			if ( error ) {
				await api.updateFailedOrder( paymentIntentId, orderId );
				throw error;
			}
		} catch ( error ) {
			$form.removeClass( 'processing' ).unblock();
			showError( error.message );
		}
	};

	/**
	 * Submits the confirmation of the setup intent to Stripe on Add Payment Method page.
	 * Stripe redirects to Payment Methods page on sucess.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @return {boolean} A flag for the event handler.
	 */
	const handleUPEAddPayment = async ( $form ) => {
		const isUPEFormValid = await checkUPEForm( $form );
		if ( ! isUPEFormValid ) {
			return;
		}

		blockUI( $form );

		try {
			const returnUrl = getStripeServerData()?.addPaymentReturnURL;

			const { error } = await api.getStripe().confirmSetup( {
				element: upeElement,
				confirmParams: {
					return_url: returnUrl,
				},
			} );
			if ( error ) {
				throw error;
			}
		} catch ( error ) {
			$form.removeClass( 'processing' ).unblock();
			showError( error.message );
		}
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
				element: upeElement,
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
				await api.updateFailedOrder(
					paymentIntentId,
					response.order_id
				);
				throw error;
			}
		} catch ( error ) {
			$form.removeClass( 'processing' ).unblock();
			showError( error.message );
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
	$( 'form.checkout' ).on( 'checkout_place_order_stripe', function () {
		if ( ! isUsingSavedPaymentMethod() ) {
			if ( isUPEEnabled && paymentIntentId ) {
				handleUPECheckout( $( this ) );
				return false;
			}
		}
	} );

	// Handle the add payment method form for WooCommerce Payments.
	$( 'form#add_payment_method' ).on( 'submit', function () {
		if ( ! $( '#wc-stripe-setup-intent' ).val() ) {
			if ( isUPEEnabled && paymentIntentId ) {
				handleUPEAddPayment( $( this ) );
				return false;
			}
		}
	} );

	// Handle the Pay for Order form if WooCommerce Payments is chosen.
	$( '#order_review' ).on( 'submit', () => {
		if ( ! isUsingSavedPaymentMethod() ) {
			if ( getStripeServerData()?.isChangingPayment ) {
				handleUPEAddPayment( $( '#order_review' ) );
				return false;
			}
			handleUPEOrderPay( $( '#order_review' ) );
			return false;
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
