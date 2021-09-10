/**
 * External dependencies
 */
import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import upePaymentMethod from './payment-method';
/* eslint-disable @woocommerce/dependency-group */
import paymentRequestPaymentMethod from 'wcstripe/blocks/payment-request';
/* eslint-enable */

// Register Stripe UPE.
registerPaymentMethod( upePaymentMethod );

// Register Stripe Payment Request.
registerExpressPaymentMethod( paymentRequestPaymentMethod );
