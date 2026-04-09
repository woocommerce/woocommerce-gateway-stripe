/* global wc_stripe_settings_params */
import React from 'react';
import { createRoot } from 'react-dom/client';
import OptimizedCheckoutFirstMethodNotice from '../../settings/advanced-settings-section/optimized-checkout-first-method-notice';
import PaymentGatewaysConfirmation from './payment-gateways-confirmation';

const paymentGatewaysContainer = document.getElementById(
	'wc-stripe-payment-gateways-container'
);

if ( paymentGatewaysContainer ) {
	createRoot( paymentGatewaysContainer ).render(
		<PaymentGatewaysConfirmation />
	);
}

/**
 * Injects the Optimized Checkout first method notice into the Stripe payment methods list.
 *
 * @return {boolean} True if the notice was injected, false otherwise.
 */
function injectNotice() {
	const stripeElement = document.querySelector( '#stripe' );
	if (
		! stripeElement ||
		stripeElement.querySelector(
			'.wc-stripe-payment-gateways-oc-notice-wrapper'
		) !== null
	) {
		return false;
	}

	const stripeDescription = stripeElement.querySelector(
		'.woocommerce-list__item-text'
	);

	if ( ! stripeDescription ) {
		return false;
	}

	const mountNode = document.createElement( 'div' );
	mountNode.className = 'wc-stripe-payment-gateways-oc-notice-wrapper';
	stripeDescription.appendChild( mountNode );

	const root = createRoot( mountNode );
	root.render(
		<OptimizedCheckoutFirstMethodNotice
			isOCEnabled={ true }
			refreshPage={ true }
		/>
	);
	return true;
}

/**
 * Runs the notice injection with a MutationObserver.
 *
 * @return {void}
 */
function runWithObserver() {
	if ( injectNotice() ) {
		return;
	}
	const observer = new MutationObserver( () => {
		if ( injectNotice() ) {
			observer.disconnect();
		}
	} );

	let observerTarget = document.querySelector(
		'.wc-settings-prevent-change-event'
	);

	if ( ! observerTarget ) {
		// fallback to the body if the observer target is not found if the settings page html changes in the future.
		observerTarget = document.body;
	}

	observer.observe( observerTarget, { childList: true, subtree: true } );
	window.setTimeout( () => observer.disconnect(), 15000 );
}

// If the Optimized Checkout is enabled and the notice should be shown, run the notice injection.
if (
	// eslint-disable-next-line camelcase
	!! wc_stripe_settings_params?.is_oc_enabled &&
	// eslint-disable-next-line camelcase
	!! wc_stripe_settings_params?.show_stripe_first_method_notice
) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', runWithObserver );
	} else {
		runWithObserver();
	}
}
