import { getSharedStripeInstance } from '../../stripe-utils/shared-stripe-instance';
import { getStripeDevWidgetOptions } from '../../stripe-utils/get-stripe-dev-widget-options';

function mountPaymentMethodMessaging() {
	const container = document.querySelector( '.wc-stripe-bnpl-messaging' );
	const config = window.wc_stripe_payment_method_messaging;
	const key = window.wc_stripe_upe_params.key;

	if ( ! container || ! key || ! config.paymentMethodTypes?.length ) {
		return;
	}

	const stripe = getSharedStripeInstance( key, {
		locale: config.locale,
		...getStripeDevWidgetOptions(),
	} );

	const messaging = stripe.elements().create( 'paymentMethodMessaging', {
		amount: Number( config.amount ),
		currency: config.currency,
		paymentMethodTypes: config.paymentMethodTypes,
		...( config.countryCode && { countryCode: config.countryCode } ),
	} );

	messaging.mount( container );
}

mountPaymentMethodMessaging();
