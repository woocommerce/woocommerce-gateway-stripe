import { randomUUID } from 'crypto';
import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api, user } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	setupOptimizedCheckout,
	selectOCCardMethod,
	fillOCDetails,
	clickPlaceOrder,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

test.describe( 'Optimized Checkout payment tests @shortcode', () => {
	test( 'customer can pay with Optimized Checkout @smoke', async ( {
		page,
		browser,
	} ) => {
		await setupOptimizedCheckout( page, 'shortcode', {
			billingDetails: {
				...config.get( 'addresses.customer.billing' ),
				email: `stripe-e2e-${ randomUUID() }@example.com`,
			},
		} );
		await fillOCDetails( page, config.get( 'cards.basic' ), 'shortcode' );

		const expectedTotal = await getCartTotal( page );

		await clickPlaceOrder( page );

		await waitForOrderReceivedPageAndConfirmExpectedTotal(
			browser,
			page,
			expectedTotal
		);
	} );

	test( 'hides the save checkbox wrapper when Link handles save consent', async ( {
		page,
	} ) => {
		// This project's account has Link on (oc-setup verifies it). With Link
		// enabled the store-level save checkbox is suppressed via the
		// wc-stripe-hide-save-checkbox body class, and the fieldset wrapping it
		// must be hidden along with it.

		// A dedicated token-less customer isolates the body-class path: with no
		// saved tokens there is no token radio list, so nothing applies an
		// inline hide to the checkbox row.
		const randomString = randomUUID();
		const linkUsername =
			randomString + '.' + config.get( 'users.customer.username' );
		await api.create.customer( {
			...config.get( 'users.customer' ),
			...config.get( 'addresses.customer' ),
			email: randomString + '+' + config.get( 'users.customer.email' ),
			username: linkUsername,
		} );

		await user.login(
			page,
			linkUsername,
			config.get( 'users.customer.password' )
		);
		await emptyCart( page );
		await setupCart( page );
		await setupShortcodeCheckout(
			page,
			config.get( 'addresses.customer.billing' )
		);

		// The save checkbox rule is card/link-only and the element's default
		// selection follows Stripe's ranking, so pick Card explicitly.
		await selectOCCardMethod( page, 'shortcode' );

		const saveCheckboxWrapper = page.locator(
			'.payment_box.payment_method_stripe fieldset:has(.woocommerce-SavedPaymentMethods-saveNew)'
		);
		await expect( saveCheckboxWrapper ).toBeAttached();
		await expect( saveCheckboxWrapper ).toBeHidden();
	} );
} );
