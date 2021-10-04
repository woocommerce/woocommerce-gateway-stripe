import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} from '@woocommerce/blocks-registry';
import stripeCcPaymentMethod from './credit-card';
import paymentRequestPaymentMethod from './payment-request';

// Register Stripe Credit Card.
registerPaymentMethod( stripeCcPaymentMethod );

// Register Stripe Payment Request.
registerExpressPaymentMethod( paymentRequestPaymentMethod );
