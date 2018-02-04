/**
 * External dependencies
 */
import config from 'config';
import { until, By } from 'selenium-webdriver';
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { Helper as wcHelper, SingleProductPage, CheckoutOrderReceivedPage } from 'wc-e2e-page-objects';

/**
 * Internal dependencies
 */
import CheckoutPage from './checkout-page';
import CustomerFlow from './customer-flow';
import GuestCustomerFlow from './guest-customer-flow';
import StoreOwnerFlow from './store-owner-flow';

let manager;
let driver;
let currentUser;

export const startBrowser = () => {
	manager = new WebDriverManager( 'chrome', { baseUrl: config.get( 'url' ) } );
	driver = manager.getDriver();
};

export const quitBrowser = () => {
	helper.clearCookiesAndDeleteLocalStorage( driver );
	manager.quitBrowser();
};

export const asStoreOwner = () => {
	helper.clearCookiesAndDeleteLocalStorage( driver );

	currentUser = new StoreOwnerFlow( driver, {
		baseUrl: config.get( 'url' ),
		username: config.get( 'users.shopmanager.username' ),
		password: config.get( 'users.shopmanager.password' )
	} );
	return currentUser;
};

export const asCustomer = () => {
	helper.clearCookiesAndDeleteLocalStorage( driver );

	currentUser = new CustomerFlow( driver, {
		baseUrl: config.get( 'url' ),
		username: config.get( 'users.customer.username' ),
		password: config.get( 'users.customer.password' )
	} );
	return currentUser;
};

export const asGuestCustomer = () => {
	helper.clearCookiesAndDeleteLocalStorage( driver );

	currentUser = new GuestCustomerFlow( driver, { baseUrl: config.get( 'url' ) } );
	return currentUser;
};

export const setStripeSettings = setting => {
	const storeOwner = asStoreOwner();
	storeOwner.setStripeSettings( setting );
	storeOwner.logout();
};

export const openOnePaymentProduct = () => {
	return new SingleProductPage( driver, {
		url: manager.getPageUrl( config.get( 'products.onePayment' ) )
	} );
};

export const payWithStripe = ( inline ) => {
	const checkout = new CheckoutPage( driver, {
		url: manager.getPageUrl( '/checkout' )
	} );

	helper.setWhenSettable( driver, By.css( '#billing_first_name' ), 'John' );
	helper.setWhenSettable( driver, By.css( '#billing_last_name' ), 'Doe' );
	helper.selectOption( driver, By.css( '#billing_country' ), 'United States' );
	helper.setWhenSettable( driver, By.css( '#billing_address_1' ), '1234 Test St.' );
	helper.setWhenSettable( driver, By.css( '#billing_address_2' ), '#020' );
	helper.setWhenSettable( driver, By.css( '#billing_city' ), 'Los Angeles' );
	helper.selectOption( driver, By.css( '#billing_state' ), 'California' );
	helper.setWhenSettable( driver, By.css( '#billing_postcode' ), '90066' );
	helper.setWhenSettable( driver, By.css( '#billing_phone' ), '8008008000' );
	helper.setWhenSettable( driver, By.css( '#billing_email' ), 'john.doe@example.com' );

	checkout.selectPaymentMethod( 'Credit Card (Stripe)' );

	wcHelper.waitTillUIBlockNotPresent( driver, 20000 );

	if ( inline ) {
		var iframeElement = driver.findElement( By.name( '__privateStripeFrame4' ) );
		driver.switchTo().frame( iframeElement );
		driver.findElement( By.name( 'cardnumber' ) ).sendKeys( '4242424242424242' );
		driver.findElement( By.name( 'exp-date' ) ).sendKeys( '1220' );
		driver.findElement( By.name( 'cvc' ) ).sendKeys( '222' );

		driver.switchTo().defaultContent();
	} else {
		var iframeElement4 = driver.findElement( By.name( '__privateStripeFrame4' ) ),
			iframeElement5 = driver.findElement( By.name( '__privateStripeFrame5' ) ),
			iframeElement6 = driver.findElement( By.name( '__privateStripeFrame6' ) );

		driver.switchTo().frame( iframeElement4 );
		driver.findElement( By.name( 'cardnumber' ) ).sendKeys( '4242424242424242' );

		driver.switchTo().defaultContent();

		driver.switchTo().frame( iframeElement5 );
		driver.findElement( By.name( 'exp-date' ) ).sendKeys( '1220' );

		driver.switchTo().defaultContent();

		driver.switchTo().frame( iframeElement6 );
		driver.findElement( By.name( 'cvc' ) ).sendKeys( '222' );

		driver.switchTo().defaultContent();
	}

	checkout.placeOrder();

	wcHelper.waitTillUIBlockNotPresent( driver, 20000 );
};

export const redirectedTo = ( urlSubstr, timeout = 10000, msg = '' ) => {
	if ( ! msg ) {
		msg = `waiting to be redirected to URL that contains "${ urlSubstr }"`;
	}
	return driver.wait( until.urlContains( urlSubstr ), timeout, msg );
};

export const getAttribute = ( selector, attr ) => {
	return driver.findElement( selector ).getAttribute( attr );
};

export const checkoutHasText = text => {
	const checkout = new CheckoutPage( driver, {
		visit: false
	} );

	return checkout.hasText( text );
};

export const orderReceivedHasText = text => {
	const orderReceived = new CheckoutOrderReceivedPage( driver, {
		visit: false
	} );

	return orderReceived.hasText( text );
};
