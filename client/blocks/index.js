import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import stripeCcPaymentMethod from './credit-card';

// Register Stripe Credit Card.
registerPaymentMethod( stripeCcPaymentMethod );
