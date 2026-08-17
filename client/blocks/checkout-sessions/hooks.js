import jQuery from 'jquery';
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { dispatch, select } from '@wordpress/data';
import { isSavePaymentMethodCheckboxChecked } from 'wcstripe/blocks/utils';
import { normalizeReturnUrl } from 'wcstripe/stripe-utils/normalize-return-url';
import { getStaleCheckoutTotalMessage } from 'wcstripe/stripe-utils/utils';
import { waitForPaymentElementCompletion } from 'wcstripe/blocks/wait-for-payment-element-completion';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 */

/**
 * Handles the Block Checkout onPaymentSetup event for the Checkout Sessions integration.
 *
 * @param {*}       onPaymentSetup                The onPaymentSetup event, which is triggered when the payment method is being set up during the checkout process.
 * @param {string}  checkoutSessionId             The ID of the checkout session, used to associate the payment method with the session.
 * @param {string}  errorMessage                  An error message to display if there was an error loading the checkout session, used to provide feedback to the user.
 * @param {Object}  hasLoadErrorRef               A ref object that indicates whether there was an error loading the checkout session, used to prevent further processing if the session failed to load.
 * @param {boolean} isPaymentElementComplete      A boolean that indicates whether the Stripe Payment Element is complete, used to validate that the user has entered all required payment information before allowing them to proceed with the payment.
 * @param {string}  selectedPaymentType           The Stripe payment method type the customer picked inside the Payment Element (e.g. 'ideal'), used so the server can set the order's payment method title to the actual method instead of the OC pseudo-method default.
 * @param {Object}  [isPaymentElementCompleteRef] Optional live mirror of isPaymentElementComplete, letting a submission during a (re)mount wait for the element to settle.
 * @param {Object}  [syncFailedRef]               Optional ref set when the last totals resync failed, so submission is blocked while the session total is stale.
 */
export const usePaymentSetupHandler = (
	onPaymentSetup,
	checkoutSessionId,
	errorMessage,
	hasLoadErrorRef,
	isPaymentElementComplete,
	selectedPaymentType,
	isPaymentElementCompleteRef = null,
	syncFailedRef = null
) => {
	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					const { validationStore } = window.wc?.wcBlocksData ?? {};
					if ( validationStore ) {
						const store = select( validationStore );
						const hasValidationErrors = store.hasValidationErrors();

						// Return if there is a validation error on the checkout fields.
						if ( hasValidationErrors ) {
							return;
						}
					}

					if ( hasLoadErrorRef.current ) {
						return {
							type: 'error',
							message: __(
								'There was an error loading the payment information. Please refresh the page and try again.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					if ( ! checkoutSessionId ) {
						return {
							type: 'error',
							message: __(
								'We could not initialize the payment session. Please refresh the page and try again.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					// The session still holds stale line items from a failed
					// resync, so its total no longer matches the cart.
					if ( syncFailedRef?.current ) {
						return {
							type: 'error',
							message: getStaleCheckoutTotalMessage(),
						};
					}

					if ( errorMessage ) {
						return {
							type: 'error',
							message: errorMessage,
						};
					}

					// Prefer the live ref so a submission mid-(re)mount can
					// wait for the element to settle.
					const isComplete = () =>
						isPaymentElementCompleteRef
							? isPaymentElementCompleteRef.current
							: isPaymentElementComplete;

					if ( ! isComplete() ) {
						if ( isPaymentElementCompleteRef ) {
							await waitForPaymentElementCompletion(
								isPaymentElementCompleteRef
							);
						}
						if ( ! isComplete() ) {
							return {
								type: 'error',
								message: __(
									'Your payment information is incomplete.',
									'woocommerce-gateway-stripe'
								),
							};
						}
					}

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: 'stripe',
								save_payment_method:
									isSavePaymentMethodCheckboxChecked()
										? 'yes'
										: 'no',
								wc_stripe_checkout_session_id:
									checkoutSessionId,
								wc_stripe_selected_upe_payment_type:
									selectedPaymentType ?? '',
							},
						},
					};
				}
				return handlePaymentProcessing();
			} ),
		[
			checkoutSessionId,
			errorMessage,
			hasLoadErrorRef,
			isPaymentElementComplete,
			isPaymentElementCompleteRef,
			onPaymentSetup,
			selectedPaymentType,
			syncFailedRef,
		]
	);
};

/**
 * Handles the Block Checkout onCheckoutSuccess event for the Checkout Sessions integration.
 *
 * @param {*}       checkoutState        The checkout state.
 * @param {*}       onCheckoutSuccess    The onCheckoutSuccess event.
 * @param {Object}  billing              The billing data from WooCommerce Blocks, containing billingAddress.
 * @param {boolean} isLoggedIn           Whether the customer is logged-in.
 * @param {boolean} isPayerPhoneRequired Whether the payer phone information is required.
 * @param {Object}  shippingData         The shipping data from WooCommerce Blocks, containing shippingAddress.
 */
export const useCheckoutSuccessHandler = (
	checkoutState,
	onCheckoutSuccess,
	billing,
	isLoggedIn,
	isPayerPhoneRequired,
	shippingData
) => {
	useEffect(
		() =>
			onCheckoutSuccess(
				async ( { processingResponse: { paymentDetails } } ) => {
					if ( checkoutState.type !== 'success' ) {
						return {
							type: 'error',
							message: __(
								'Checkout is not ready for confirmation.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					const billingAddress = billing?.billingAddress;
					const shippingAddress = shippingData?.shippingAddress;

					const { redirect } = paymentDetails;
					const { checkout } = checkoutState;

					const confirmArgs = {
						billingAddress: {
							name: `${ billingAddress?.first_name ?? '' } ${
								billingAddress?.last_name ?? ''
							}`.trim(),
							address: {
								country: billingAddress?.country,
								line1: billingAddress?.address_1,
								line2: billingAddress?.address_2,
								state: billingAddress?.state,
								city: billingAddress?.city,
								postal_code: billingAddress?.postcode,
							},
						},
						returnUrl: normalizeReturnUrl( redirect ),
						redirect: 'if_required',
					};

					if ( isLoggedIn ) {
						confirmArgs.savePaymentMethod =
							isSavePaymentMethodCheckboxChecked();
					}

					// Only include shipping information if the min. requirement is met.
					if (
						shippingAddress?.address_1 &&
						shippingAddress?.country
					) {
						confirmArgs.shippingAddress = {
							address: {
								country: shippingAddress?.country,
								line1: shippingAddress?.address_1,
							},
						};

						// API do not accept empty values.
						if ( shippingAddress?.recipient ) {
							confirmArgs.shippingAddress.name =
								shippingAddress?.recipient.trim();
						}

						// If the shipping address name is still empty, attempt to use the billing name (it is a required parameter).
						if ( ! confirmArgs.shippingAddress.name ) {
							confirmArgs.shippingAddress.name = `${
								billingAddress?.first_name ?? ''
							} ${ billingAddress?.last_name ?? '' }`.trim();
						}

						if ( shippingAddress?.address_2 ) {
							confirmArgs.shippingAddress.address.line2 =
								shippingAddress?.address_2;
						}

						if ( shippingAddress?.state ) {
							confirmArgs.shippingAddress.address.state =
								shippingAddress?.state;
						}

						if ( shippingAddress?.city ) {
							confirmArgs.shippingAddress.address.city =
								shippingAddress?.city;
						}

						if ( shippingAddress?.postcode ) {
							confirmArgs.shippingAddress.address.postal_code =
								shippingAddress?.postcode;
						}
					}

					if ( ! checkout.email ) {
						// If checkout session doesn't have email, attempt to get it from the checkout form.
						const userEmail =
							document.getElementById( 'email' )?.value;
						if ( userEmail ) {
							confirmArgs.email = userEmail;
						}
					}

					if ( isPayerPhoneRequired ) {
						const userPhone =
							document.getElementById( 'billing-phone' )?.value ||
							document.getElementById( 'shipping-phone' )?.value;
						if ( userPhone ) {
							confirmArgs.phoneNumber = userPhone;
						}
					}

					const confirmResult = await checkout.confirm( confirmArgs );
					if ( confirmResult?.type === 'error' ) {
						return {
							type: 'error',
							message:
								confirmResult.error?.message ??
								'Payment confirmation failed.',
						};
					}

					// If no error, we assume success for now. This return value is never used, as the `confirm` call indicates success.
					return {
						type: 'success',
					};
				}
			),
		[
			onCheckoutSuccess,
			checkoutState,
			billing,
			isLoggedIn,
			isPayerPhoneRequired,
			shippingData,
		]
	);
};

/**
 * Handles the Block Checkout onCheckoutFail event for the Checkout Sessions integration.
 *
 * @param {*}                 onCheckoutFail The onCheckoutFail event.
 * @param {EmitResponseProps} emitResponse   Various helpers for usage with observer.
 */
export const usePaymentFailHandler = ( onCheckoutFail, emitResponse ) => {
	useEffect(
		() =>
			onCheckoutFail( ( { processingResponse: { paymentDetails } } ) => {
				return {
					type: 'failure',
					message:
						paymentDetails?.errorMessage ??
						'An error occurred during payment processing.',
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			} ),
		[ onCheckoutFail, emitResponse ]
	);
};

/**
 * Notifies Stripe.js after a native Store API cart response embeds a newer Checkout Session revision.
 * The Store API extension synchronizes Stripe while WooCommerce serializes its fully calculated cart.
 *
 * @param {string|null} checkoutSessionId Stripe Checkout Session id once the session is ready.
 * @param {Object}      checkoutState     Result of useCheckout() from @stripe/react-stripe-js/checkout.
 * @param {Object}      [syncFailedRef]   Optional ref flagged true when a resync fails (stale session) and false once it succeeds.
 * @param {Object}      [sessionData]     Session data embedded in cartData.extensions.
 */
export const useCheckoutSessionTotalsSync = (
	checkoutSessionId,
	checkoutState,
	syncFailedRef = null,
	sessionData = {}
) => {
	const checkoutStateRef = useRef( checkoutState );

	const prevSessionIdRef = useRef( null );
	const prevSessionStateRef = useRef( null );
	const sessionState = `${ sessionData?.revision ?? '' }:${
		sessionData?.status ?? 'uninitialized'
	}`;
	const isCheckoutReady = checkoutState?.type === 'success';

	useEffect( () => {
		if ( checkoutStateRef.current !== checkoutState ) {
			checkoutStateRef.current = checkoutState;
		}
	}, [ checkoutState, checkoutStateRef ] );

	// A replacement session has its own revision sequence, so do not compare it with the previous one.
	useEffect( () => {
		if ( prevSessionIdRef.current !== checkoutSessionId ) {
			prevSessionIdRef.current = checkoutSessionId;
			prevSessionStateRef.current = null;
		}
	}, [ checkoutSessionId ] );

	useEffect( () => {
		if ( ! checkoutSessionId ) {
			return;
		}

		let cancelled = false;

		// Stable id so a later successful resync can retract the notice a failed one showed.
		const STALE_TOTAL_NOTICE_ID = 'wc-stripe-stale-checkout-total';

		const markSyncFailed = () => {
			if ( cancelled ) {
				return;
			}
			if ( syncFailedRef ) {
				syncFailedRef.current = true;
			}
			dispatch( 'core/notices' )?.createErrorNotice(
				getStaleCheckoutTotalMessage(),
				{
					id: STALE_TOTAL_NOTICE_ID,
					context: 'wc/checkout/payments',
				}
			);
		};

		const clearSyncFailed = () => {
			if ( syncFailedRef ) {
				syncFailedRef.current = false;
			}
			dispatch( 'core/notices' )?.removeNotice(
				STALE_TOTAL_NOTICE_ID,
				'wc/checkout/payments'
			);
		};

		if ( prevSessionStateRef.current === null ) {
			prevSessionStateRef.current = sessionState;
			// If the session has an error, ensure we mark the sync as failed, as errors
			// may not bump the revision, and may not be distinguishable from each other.
			if ( sessionData?.status === 'error' ) {
				markSyncFailed();
			}
			return;
		}

		if ( prevSessionStateRef.current === sessionState ) {
			return;
		}

		// If the checkout is not ready, do not trigger the (re)sync.
		const state = checkoutStateRef.current;
		if ( state?.type !== 'success' ) {
			return;
		}

		const uiBlockSelector =
			'.wc-block-checkout__payment-method, .wc-block-components-checkout-place-order-button';

		// Keep a reference to the previous session state so we can restore it
		// if we cancel the current resync before it completes.
		const prevSessionState = prevSessionStateRef.current;
		prevSessionStateRef.current = sessionState;

		let syncCompleted = false;

		const run = async () => {
			try {
				if ( sessionData?.status === 'error' ) {
					markSyncFailed();
					return;
				}

				blockUI( jQuery( uiBlockSelector ) );
				const { checkout } = state;
				if ( typeof checkout?.runServerUpdate !== 'function' ) {
					markSyncFailed();
					return;
				}

				const result = await checkout.runServerUpdate( async () => {} );
				if ( ! cancelled && result && result.type === 'error' ) {
					markSyncFailed();
					// eslint-disable-next-line no-console
					console.error( result.error );
				} else if ( ! cancelled ) {
					// Totals are back in sync; lift the block and retract the notice.
					clearSyncFailed();
				}
			} catch ( error ) {
				if ( ! cancelled ) {
					markSyncFailed();
					// eslint-disable-next-line no-console
					console.error( error );
				}
			} finally {
				syncCompleted = true;
				// Only unblock the UI if we don't have a newer resync in flight,
				// which is indicated by the cancelled flag remaining false.
				if ( ! cancelled ) {
					unblockUI( jQuery( uiBlockSelector ) );
				}
			}
		};

		run();

		return () => {
			cancelled = true;
			// If the run was cancelled mid-flight and the sync has not completed yet,
			// we need to restore the previous session state and unblock the UI.
			if ( ! syncCompleted ) {
				prevSessionStateRef.current = prevSessionState;
				unblockUI( jQuery( uiBlockSelector ) );
			}
		};
	}, [
		checkoutSessionId,
		isCheckoutReady,
		sessionData?.status,
		sessionState,
		syncFailedRef,
	] );
};

/**
 * Block UI to indicate processing and avoid duplicate submission.
 *
 * @param {Object} $target The jQuery object for the target element.
 */
const blockUI = ( $target ) => {
	$target.addClass( 'processing' ).block( {
		message: null,
		overlayCSS: { background: '#fff', opacity: 0.6 },
	} );
};

/**
 * Unblock UI to remove the processing state from the element of the form.
 *
 * @param {Object} $target The jQuery object for the target element.
 */
const unblockUI = ( $target ) => {
	$target.removeClass( 'processing' ).unblock();
};
