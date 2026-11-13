import jQuery from 'jquery';
import { initializeVoucherAndWalletConfirmation } from '../voucher-wallet-confirmation';
import {
	getStripeServerData,
	showErrorCheckout,
	unblockBlockCheckout,
	resetBlockCheckoutPaymentState,
} from 'wcstripe/stripe-utils';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeServerData: jest.fn(),
	normalizeReturnUrl: jest.requireActual( '../normalize-return-url' )
		.normalizeReturnUrl,
	showErrorCheckout: jest.fn(),
	unblockBlockCheckout: jest.fn(),
	resetBlockCheckoutPaymentState: jest.fn(),
} ) );

const methods = [
	[ 'boleto', 'voucher', 'confirmBoletoPayment' ],
	[ 'oxxo', 'voucher', 'confirmOxxoPayment' ],
	[ 'multibanco', 'voucher', 'confirmMultibancoPayment' ],
	[ 'cashapp', 'wallet', 'confirmCashappPayment' ],
	[ 'wechat_pay', 'wallet', 'confirmWechatPayPayment' ],
];
const returnUrl =
	'http://localhost/checkout/order-received/123/?key=wc_order_test';
const originalLocation = window.location;
let stripe;
let api;

const setHash = (
	method,
	type,
	intentType = 'payment_intent',
	orderId = '123'
) => {
	const intent = type === 'wallet' ? `${ intentType }:` : '';
	const nonce = type === 'wallet' ? ':test_nonce' : '';
	window.location.hash = `#wc-stripe-${ type }-${ orderId }:${ method }:${ intent }secret:${ encodeURIComponent(
		returnUrl
	) }${ nonce }`;
	window.location.href = `http://localhost/checkout/${ window.location.hash }`;
};

beforeEach( () => {
	jest.clearAllMocks();
	delete window.location;
	window.location = {
		href: 'http://localhost/checkout/',
		hash: '',
		origin: 'http://localhost',
		pathname: '/checkout/',
		search: '',
	};
	jest.spyOn( window.history, 'replaceState' ).mockImplementation( () => {
		window.location.hash = '';
	} );
	jQuery.fn.block = jest.fn().mockReturnThis();
	jQuery.fn.unblock = jest.fn().mockReturnThis();
	getStripeServerData.mockReturnValue( { isCheckout: true } );
	document.body.innerHTML = '<div class="wc-block-checkout"></div>';
	stripe = Object.fromEntries(
		methods.map( ( [ , , name ] ) => [
			name,
			jest.fn().mockResolvedValue( {
				paymentIntent: { status: 'requires_action' },
			} ),
		] )
	);
	stripe.confirmCashappSetup = jest
		.fn()
		.mockResolvedValue( { setupIntent: { status: 'requires_action' } } );
	api = {
		getStripe: () => stripe,
		request: jest.fn().mockResolvedValue( {
			success: true,
			data: { return_url: returnUrl },
		} ),
		getAjaxUrl: jest.fn().mockReturnValue( '/ajax' ),
	};
} );

afterEach( () => {
	jQuery( window ).off( '.wcStripeVoucherWallet' );
	jQuery.removeData( window, 'wcStripeVoucherWalletConfirmation' );
	window.location = originalLocation;
	document.body.innerHTML = '';
	delete jQuery.fn.block;
	delete jQuery.fn.unblock;
} );

it.each( methods )(
	'confirms %s on initial load without a classic form',
	async ( method, type, confirmMethod ) => {
		setHash( method, type );
		await initializeVoucherAndWalletConfirmation( api );
		expect( stripe[ confirmMethod ] ).toHaveBeenCalledWith(
			'secret',
			expect.any( Object )
		);
		expect( window.location.hash ).toBe( '' );
		expect( window.location.href === returnUrl ).toBe( type === 'voucher' );
		expect( unblockBlockCheckout ).toHaveBeenCalledTimes(
			type === 'wallet' ? 1 : 0
		);
		expect( resetBlockCheckoutPaymentState ).toHaveBeenCalledTimes(
			type === 'wallet' ? 1 : 0
		);
	}
);

it.each( methods )(
	'confirms %s after the checkout sets the hash',
	async ( method, type, confirmMethod ) => {
		await initializeVoucherAndWalletConfirmation( api );
		setHash( method, type );
		await jQuery( window ).triggerHandler( 'hashchange' );
		expect( stripe[ confirmMethod ] ).toHaveBeenCalledTimes( 1 );
	}
);

it.each( [ {}, { isCheckout: false } ] )(
	'ignores hashes outside checkout and order-pay contexts: %j',
	async ( settings ) => {
		getStripeServerData.mockReturnValue( settings );
		setHash( 'cashapp', 'wallet' );
		await initializeVoucherAndWalletConfirmation( api );
		await jQuery( window ).triggerHandler( 'hashchange' );
		expect( stripe.confirmCashappPayment ).not.toHaveBeenCalled();
	}
);

it( 'ignores unrelated fragments', async () => {
	window.location.hash = '#wc-stripe-confirm-pi:secret';
	await initializeVoucherAndWalletConfirmation( api );
	for ( const confirmMethod of Object.values( stripe ) ) {
		expect( confirmMethod ).not.toHaveBeenCalled();
	}
} );

it( 'does not duplicate the listener when both bundles initialize', async () => {
	await initializeVoucherAndWalletConfirmation( api );
	const sharedUtils = jest.requireMock( 'wcstripe/stripe-utils' );
	jest.isolateModules( () => {
		// WordPress supplies the same jQuery instance to separate webpack bundles.
		jest.doMock( 'jquery', () => jQuery );
		jest.doMock( 'wcstripe/stripe-utils', () => sharedUtils );
		const otherBundle = require( '../voucher-wallet-confirmation' );
		otherBundle.initializeVoucherAndWalletConfirmation( api );
	} );
	setHash( 'cashapp', 'wallet' );
	await jQuery( window ).triggerHandler( 'hashchange' );
	expect( stripe.confirmCashappPayment ).toHaveBeenCalledTimes( 1 );
	expect( jQuery._data( window, 'events' ).hashchange ).toHaveLength( 1 );
} );

it( 'ignores repeated events during confirmation but allows retry after cancellation', async () => {
	let finish;
	stripe.confirmCashappPayment.mockImplementationOnce(
		() =>
			new Promise( ( resolve ) => {
				finish = resolve;
			} )
	);
	setHash( 'cashapp', 'wallet' );
	const pending = initializeVoucherAndWalletConfirmation( api );
	setHash( 'cashapp', 'wallet' );
	await jQuery( window ).triggerHandler( 'hashchange' );
	expect( stripe.confirmCashappPayment ).toHaveBeenCalledTimes( 1 );
	expect( window.location.hash ).toBe( '' );
	finish( { paymentIntent: { status: 'requires_action' } } );
	await pending;
	setHash( 'cashapp', 'wallet' );
	await jQuery( window ).triggerHandler( 'hashchange' );
	expect( stripe.confirmCashappPayment ).toHaveBeenCalledTimes( 2 );
} );

it( 'allows retry after a Stripe error', async () => {
	stripe.confirmCashappPayment.mockRejectedValueOnce(
		new Error( 'Declined' )
	);
	setHash( 'cashapp', 'wallet' );
	await initializeVoucherAndWalletConfirmation( api );
	expect( showErrorCheckout ).toHaveBeenCalledWith( 'Declined' );
	expect( resetBlockCheckoutPaymentState ).toHaveBeenCalled();
	setHash( 'cashapp', 'wallet' );
	await jQuery( window ).triggerHandler( 'hashchange' );
	expect( stripe.confirmCashappPayment ).toHaveBeenCalledTimes( 2 );
} );

it( 'handles Cash App setup intents when changing a subscription payment method', async () => {
	getStripeServerData.mockReturnValue( { isChangingPayment: true } );
	document.body.innerHTML =
		'<form id="order_review" class="processing"></form>';
	stripe.confirmCashappSetup.mockResolvedValue( {
		setupIntent: {
			id: 'seti_test',
			status: 'succeeded',
			payment_method: 'pm_test',
		},
	} );
	setHash( 'cashapp', 'wallet', 'setup_intent' );
	await initializeVoucherAndWalletConfirmation( api );
	expect( stripe.confirmCashappSetup ).toHaveBeenCalledWith( 'secret', {
		return_url: returnUrl,
	} );
	expect( api.request ).toHaveBeenCalledWith( '/ajax', {
		order_id: '123',
		intent_id: 'seti_test',
		payment_method_id: 'pm_test',
		_ajax_nonce: 'test_nonce',
	} );
	expect( window.location.href ).toBe( returnUrl );
	expect( jQuery( '#order_review' ).hasClass( 'processing' ) ).toBe( false );
} );

it.each( [
	[ 'boleto', 'voucher' ],
	[ 'cashapp', 'wallet' ],
] )(
	'blocks the order-pay form for %s and rejects a different order ID',
	async ( method, type ) => {
		getStripeServerData.mockReturnValue( {
			isOrderPay: true,
			orderId: '456',
		} );
		document.body.innerHTML = '<form id="order_review"></form>';
		setHash( method, type );
		await initializeVoucherAndWalletConfirmation( api );
		expect( jQuery.fn.block ).toHaveBeenCalled();
		expect( jQuery.fn.block.mock.instances[ 0 ][ 0 ].id ).toBe(
			'order_review'
		);
		expect( jQuery( '#order_review' ).hasClass( 'processing' ) ).toBe(
			false
		);
		for ( const confirmMethod of Object.values( stripe ) ) {
			expect( confirmMethod ).not.toHaveBeenCalled();
		}
	}
);
