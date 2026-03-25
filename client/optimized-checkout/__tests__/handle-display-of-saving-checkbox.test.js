import { handleDisplayOfSavingCheckbox } from 'wcstripe/optimized-checkout/handle-display-of-saving-checkbox';
import {
	PAYMENT_METHOD_ALIPAY,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_LINK,
	PAYMENT_METHOD_SEPA,
} from 'wcstripe/stripe-utils/constants';
import { isLinkEnabled } from 'wcstripe/stripe-utils';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	...jest.requireActual( 'wcstripe/stripe-utils' ),
	isLinkEnabled: jest.fn().mockReturnValue( false ),
	getStripeServerData: jest.fn( () => global.wc_stripe_upe_params ),
} ) );

const HIDE_CLASS = 'wc-stripe-hide-save-checkbox';
const isHidden = () => document.body.classList.contains( HIDE_CLASS );

/**
 * Helper to set up DOM with the save-card-info container for block checkout tests.
 */
const setupBlockCheckoutDOM = () => {
	const el = document.createElement( 'div' );
	el.className = 'wc-block-components-payment-methods__save-card-info';
	document.body.appendChild( el );
	return el;
};

/**
 * Builds an OC paymentMethodsConfig with showSaveOptionByMethod.
 *
 * @param {Object} byMethod Per-method showSaveOption map.
 * @return {Object} Config object matching the PHP-provided shape.
 */
const makeOCConfig = ( byMethod ) => ( {
	card: {
		enabledPaymentMethods: Object.keys( byMethod ),
		showSaveOptionByMethod: byMethod,
	},
} );

describe( 'handleDisplayOfSavingCheckbox', () => {
	beforeEach( () => {
		isLinkEnabled.mockReturnValue( false );
	} );

	afterEach( () => {
		document.body.classList.remove( HIDE_CLASS );
		document.body.textContent = '';
	} );

	describe( 'Block checkout (OC with showSaveOptionByMethod)', () => {
		it( 'Hides checkbox when card showSaveOption is false (Link enabled)', () => {
			setupBlockCheckoutDOM();

			const config = makeOCConfig( {
				card: false,
				sepa_debit: true,
			} );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD, config );
			expect( isHidden() ).toBe( true );
		} );

		it( 'Shows checkbox when SEPA showSaveOption is true', () => {
			setupBlockCheckoutDOM();

			const config = makeOCConfig( {
				card: false,
				sepa_debit: true,
			} );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_SEPA, config );
			expect( isHidden() ).toBe( false );
		} );

		it( 'Shows checkbox when card showSaveOption is true (Link disabled)', () => {
			setupBlockCheckoutDOM();

			const config = makeOCConfig( {
				card: true,
				sepa_debit: true,
			} );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD, config );
			expect( isHidden() ).toBe( false );
		} );

		it( 'Hides checkbox for non-reusable methods regardless of config', () => {
			setupBlockCheckoutDOM();

			const config = makeOCConfig( {
				card: true,
				alipay: true,
			} );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY, config );
			expect( isHidden() ).toBe( true );
		} );

		it( 'Adds body class even when save checkbox is not yet in the DOM', () => {
			// Only the block checkout wrapper exists, save checkbox not yet rendered.
			const el = document.createElement( 'div' );
			el.className = 'wc-block-checkout';
			document.body.appendChild( el );

			const config = makeOCConfig( { card: false } );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD, config );
			expect( isHidden() ).toBe( true );
		} );

		it( 'Removes body class when switching from hidden to visible', () => {
			setupBlockCheckoutDOM();

			const config = makeOCConfig( {
				card: false,
				sepa_debit: true,
			} );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD, config );
			expect( isHidden() ).toBe( true );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_SEPA, config );
			expect( isHidden() ).toBe( false );
		} );

		it( 'Falls back to showing checkbox when method is not in the map', () => {
			setupBlockCheckoutDOM();

			const config = makeOCConfig( { card: false } );

			// SEPA not in the map — should default to showing.
			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_SEPA, config );
			expect( isHidden() ).toBe( false );
		} );

		it( 'Shows checkbox when no config is provided (non-OC blocks fallback)', () => {
			setupBlockCheckoutDOM();

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( isHidden() ).toBe( false );
		} );
	} );

	describe( 'Classic checkout with OC (no block DOM, config map present)', () => {
		it( 'Hides checkbox via body class when card showSaveOption is false', () => {
			// No block checkout DOM elements at all.
			const config = makeOCConfig( {
				card: false,
				sepa_debit: true,
			} );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD, config );
			expect( isHidden() ).toBe( true );
		} );

		it( 'Shows checkbox via body class when card showSaveOption is true', () => {
			const config = makeOCConfig( {
				card: true,
				sepa_debit: true,
			} );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD, config );
			expect( isHidden() ).toBe( false );
		} );

		it( 'Hides checkbox for non-reusable methods regardless of config', () => {
			const config = makeOCConfig( {
				card: true,
				alipay: true,
			} );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY, config );
			expect( isHidden() ).toBe( true );
		} );

		it( 'Removes body class when switching from hidden to visible', () => {
			const config = makeOCConfig( {
				card: false,
				sepa_debit: true,
			} );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD, config );
			expect( isHidden() ).toBe( true );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_SEPA, config );
			expect( isHidden() ).toBe( false );
		} );
	} );

	describe( 'shouldHideSaveCheckbox error handling', () => {
		const globalValues = global.wc_stripe_upe_params;

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
		} );

		it( 'Shows checkbox when isLinkEnabled throws an exception', () => {
			isLinkEnabled.mockImplementation( () => {
				throw new Error( 'Unexpected error' );
			} );
			global.wc_stripe_upe_params = { isLoggedIn: true };

			document.body.innerHTML =
				'<div class="woocommerce-SavedPaymentMethods-saveNew"></div>';

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( saveCardInfoContainer.style.display ).toBe( 'block' );
		} );
	} );

	describe( 'Classic checkout', () => {
		const globalValues = global.wc_stripe_upe_params;

		beforeEach( () => {
			global.wc_stripe_upe_params = {
				isLoggedIn: true,
				isSignupOnCheckoutAllowed: true,
			};
		} );

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
		} );

		it( 'User is logged in', () => {
			global.wc_stripe_upe_params = { isLoggedIn: true };

			document.body.innerHTML = `
			<div class="woocommerce-SavedPaymentMethods-saveNew"></div>
			`;

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			expect( saveCardInfoContainer.style.display ).toBe( '' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( saveCardInfoContainer.style.display ).toBe( 'block' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );
		} );

		it( 'User is logged out, but the signup feature is enabled and the option selected', () => {
			global.wc_stripe_upe_params = {
				isLoggedIn: false,
				isSignupOnCheckoutAllowed: true,
			};

			document.body.innerHTML = `
				<input type="checkbox" id="createaccount" checked />
				<div class="woocommerce-SavedPaymentMethods-saveNew"></div>
			`;

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			expect( saveCardInfoContainer.style.display ).toBe( '' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( saveCardInfoContainer.style.display ).toBe( 'block' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );
		} );

		it( 'User is logged out, the signup feature is enabled, the option selected, but saved payment method selected', () => {
			global.wc_stripe_upe_params = {
				isLoggedIn: false,
				isSignupOnCheckoutAllowed: true,
			};

			document.body.innerHTML = `
				<input type="checkbox" id="createaccount" checked />
				<input type="hidden" name="wc-stripe-payment-token" value="token_123" />
				<div id="wc-stripe-upe-form" style="display: none;"></div>
				<div class="woocommerce-SavedPaymentMethods-saveNew"></div>
			`;

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			expect( saveCardInfoContainer.style.display ).toBe( '' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );
		} );

		it( 'Hides store checkbox when Link is enabled and card is selected (logged in)', () => {
			isLinkEnabled.mockReturnValue( true );
			global.wc_stripe_upe_params = { isLoggedIn: true };

			document.body.innerHTML =
				'<div class="woocommerce-SavedPaymentMethods-saveNew"></div>';

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );
		} );

		it( 'Hides store checkbox when PE reports type link (logged in)', () => {
			isLinkEnabled.mockReturnValue( true );
			global.wc_stripe_upe_params = { isLoggedIn: true };

			document.body.innerHTML =
				'<div class="woocommerce-SavedPaymentMethods-saveNew"></div>';

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_LINK );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );
		} );

		it( 'Shows store checkbox for non-card methods even when Link is enabled (logged in)', () => {
			isLinkEnabled.mockReturnValue( true );
			global.wc_stripe_upe_params = { isLoggedIn: true };

			document.body.innerHTML =
				'<div class="woocommerce-SavedPaymentMethods-saveNew"></div>';

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_SEPA );
			expect( saveCardInfoContainer.style.display ).toBe( 'block' );
		} );

		it( 'Hides store checkbox when Link is enabled and card is selected (logged out with signup)', () => {
			isLinkEnabled.mockReturnValue( true );
			global.wc_stripe_upe_params = {
				isLoggedIn: false,
				isSignupOnCheckoutAllowed: true,
			};

			document.body.innerHTML =
				'<input type="checkbox" id="createaccount" checked /><div class="woocommerce-SavedPaymentMethods-saveNew"></div>';

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );
		} );

		it( 'Hides store checkbox when Link is enabled and card is selected (saved payment method present)', () => {
			isLinkEnabled.mockReturnValue( true );
			global.wc_stripe_upe_params = { isLoggedIn: true };

			document.body.innerHTML =
				'<input type="hidden" name="wc-stripe-payment-token" value="token_123" /><div id="wc-stripe-upe-form" style="display: none;"></div><div class="woocommerce-SavedPaymentMethods-saveNew"></div>';

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );
		} );

		it( 'User is logged out, and the signup option is not available', () => {
			global.wc_stripe_upe_params = {
				isLoggedIn: false,
				isSignupOnCheckoutAllowed: false,
			};

			document.body.innerHTML = `
			<div class="woocommerce-SavedPaymentMethods-saveNew"></div>
			`;

			const saveCardInfoContainer = document.querySelector(
				'.woocommerce-SavedPaymentMethods-saveNew'
			);

			expect( saveCardInfoContainer.style.display ).toBe( '' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );
		} );
	} );
} );
