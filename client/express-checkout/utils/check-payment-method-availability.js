import { createRoot } from 'react-dom/client';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';
import { memoize } from 'lodash';
import {
	getExpressCheckoutData,
	getPaymentMethodTypesForExpressMethod,
	isManualPaymentMethodCreation,
} from 'wcstripe/express-checkout/utils';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_LINK,
} from 'wcstripe/stripe-utils/constants';
import { transformPriceWithMinorUnits } from 'wcstripe/express-checkout/transformers/wc-to-stripe';

// Each hidden probe is a full Stripe iframe, and one mount reports availability
// for every wallet it enables. Identically configured Apple/Google Pay share a
// probe; Link ('link' PM type) and Amazon Pay (no manual creation) need their own.
const PROBE_GROUPS = {
	[ EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY ]: 'wallets',
	[ EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY ]: 'wallets',
	[ EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY ]:
		EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
	[ EXPRESS_PAYMENT_METHOD_SETTING_LINK ]:
		EXPRESS_PAYMENT_METHOD_SETTING_LINK,
};

const PROBE_CONFIGS = {
	wallets: {
		representativeType: EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
		paymentMethods: {
			amazonPay: 'never',
			applePay: 'always',
			googlePay: 'always',
			link: 'never',
			paypal: 'never',
		},
	},
	[ EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY ]: {
		representativeType: EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
		paymentMethods: {
			amazonPay: 'auto',
			applePay: 'never',
			googlePay: 'never',
			link: 'never',
			paypal: 'never',
		},
	},
	[ EXPRESS_PAYMENT_METHOD_SETTING_LINK ]: {
		representativeType: EXPRESS_PAYMENT_METHOD_SETTING_LINK,
		paymentMethods: {
			amazonPay: 'never',
			applePay: 'never',
			googlePay: 'never',
			link: 'auto',
			paypal: 'never',
		},
	},
};

/**
 * Mounts one invisible Express Checkout Element for a probe group and resolves
 * with the `availablePaymentMethods` map its ready event reports. Memoized per group.
 *
 * @param {string} groupKey The probe group identifier (see PROBE_GROUPS).
 * @param {Object} api      The WCStripeAPI instance used to load Stripe.
 * @param {Object} cart     The WooCommerce cart object containing totals and currency info.
 * @return {Promise<Object|null>} Promise resolving to the availability map, or null on load error.
 */
const probeAvailablePaymentMethods = memoize( ( groupKey, api, cart ) => {
	return new Promise( ( resolve ) => {
		const { representativeType, paymentMethods } =
			PROBE_CONFIGS[ groupKey ];
		const hasFreeTrial = getExpressCheckoutData( 'has_free_trial' );

		// Create the DIV container on the fly
		const containerEl = document.createElement( 'div' );

		// Ensure the element is hidden and doesn’t interfere with the page layout.
		containerEl.style.display = 'none';

		document.querySelector( 'body' ).appendChild( containerEl );

		const root = createRoot( containerEl );

		const amount = transformPriceWithMinorUnits(
			cart.cartTotals.total_price,
			cart.cartTotals.currency_minor_unit
		);

		root.render(
			<Elements
				stripe={ api.loadStripe() }
				options={ {
					mode: hasFreeTrial ? 'subscription' : 'payment',
					...( isManualPaymentMethodCreation(
						representativeType,
						hasFreeTrial
					) && {
						paymentMethodCreation: 'manual',
					} ),
					amount: Number( amount ),
					currency: cart.cartTotals.currency_code.toLowerCase(),
					paymentMethodTypes:
						getPaymentMethodTypesForExpressMethod(
							representativeType
						),
				} }
			>
				<ExpressCheckoutElement
					onLoadError={ () => resolve( null ) }
					options={ { paymentMethods } }
					onReady={ ( event ) => {
						resolve( event.availablePaymentMethods ?? null );
						root.unmount();
						containerEl.remove();
					} }
				/>
			</Elements>
		);
	} );
} );

/**
 * Checks whether an express payment method is available in the current context
 * via its probe group's availability map.
 *
 * @param {string} paymentMethod The express payment method identifier (e.g. 'googlePay', 'applePay').
 * @param {Object} api           The WCStripeAPI instance used to load Stripe.
 * @param {Object} cart          The WooCommerce cart object containing totals and currency info.
 * @return {Promise<boolean>} Promise that resolves to true if the payment method is available, false otherwise.
 */
export const checkPaymentMethodIsAvailable = ( paymentMethod, api, cart ) =>
	probeAvailablePaymentMethods(
		PROBE_GROUPS[ paymentMethod ] ?? paymentMethod,
		api,
		cart
	).then(
		( availablePaymentMethods ) =>
			!! availablePaymentMethods?.[ paymentMethod ]
	);
