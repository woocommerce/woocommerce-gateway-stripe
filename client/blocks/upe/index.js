import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} from '@woocommerce/blocks-registry';
import upePaymentMethod from './payment-method';
import paymentRequestPaymentMethod from 'wcstripe/blocks/payment-request';

// Register Stripe UPE.
registerPaymentMethod( upePaymentMethod );

// Register Stripe Payment Request.
registerExpressPaymentMethod( paymentRequestPaymentMethod );
