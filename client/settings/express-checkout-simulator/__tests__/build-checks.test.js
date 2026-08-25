import { getSetting } from '@woocommerce/settings';
import {
	STATUS,
	buildBaseChecks,
	buildCurrencyCheck,
	buildCardMethodCheck,
	buildLocations,
} from '../build-checks';

jest.mock( '@woocommerce/settings', () => ( {
	getSetting: jest.fn(),
} ) );

describe( 'buildBaseChecks', () => {
	const params = {
		is_account_connected: true,
		is_https: true,
		is_test_mode: false,
	};

	const build = ( overrides = {}, methodEnabled = true ) =>
		buildBaseChecks( {
			params: { ...params, ...overrides },
			methodEnabled,
			methodLabel: 'Apple Pay / Google Pay',
		} );

	const byKey = ( checks, key ) => checks.find( ( c ) => c.key === key );

	it( 'passes every gate on a connected, enabled, HTTPS live setup', () => {
		const checks = build();

		expect( checks.map( ( c ) => c.key ) ).toEqual( [
			'account-connected',
			'method-enabled',
			'https',
			'mode',
		] );
		expect( byKey( checks, 'account-connected' ).status ).toBe(
			STATUS.PASS
		);
		expect( byKey( checks, 'method-enabled' ).status ).toBe( STATUS.PASS );
		expect( byKey( checks, 'https' ).status ).toBe( STATUS.PASS );
		expect( byKey( checks, 'mode' ) ).toMatchObject( {
			status: STATUS.INFO,
			detail: 'Live',
		} );
	} );

	it( 'fails the account check with a detail when disconnected', () => {
		const check = byKey(
			build( { is_account_connected: false } ),
			'account-connected'
		);

		expect( check.status ).toBe( STATUS.FAIL );
		expect( check.detail ).not.toBe( '' );
		expect( check.blockingText ).toBe( 'Stripe account is not connected.' );
	} );

	it( 'fails the method check when the method is disabled', () => {
		const check = byKey( build( {}, false ), 'method-enabled' );

		expect( check.status ).toBe( STATUS.FAIL );
		expect( check.blockingText ).toBe(
			"Apple Pay / Google Pay isn't enabled."
		);
	} );

	it( 'treats missing HTTPS as informational in test mode', () => {
		const checks = build( { is_https: false, is_test_mode: true } );

		expect( byKey( checks, 'https' ) ).toMatchObject( {
			status: STATUS.INFO,
			detail: 'Not required in test mode.',
		} );
		expect( byKey( checks, 'mode' ).detail ).toBe( 'Test' );
	} );

	it( 'fails on missing HTTPS in live mode', () => {
		const check = byKey( build( { is_https: false } ), 'https' );

		expect( check.status ).toBe( STATUS.FAIL );
		expect( check.detail ).toBe( 'Required in live mode.' );
		expect( check.blockingText ).not.toBe( '' );
	} );
} );

describe( 'buildCurrencyCheck', () => {
	const build = ( currencies ) =>
		buildCurrencyCheck( {
			currencies,
			methodLabel: 'Amazon Pay',
		} );

	it( 'returns null when the method supports all currencies', () => {
		expect( build( [] ) ).toBeNull();
	} );

	it( 'returns null when no currency list is localized', () => {
		expect( build( undefined ) ).toBeNull();
	} );

	it( 'passes when the store currency is supported', () => {
		getSetting.mockReturnValue( { code: 'EUR' } );

		expect( build( [ 'USD', 'EUR' ] ) ).toMatchObject( {
			status: STATUS.PASS,
			detail: 'Supported: USD, EUR',
		} );
	} );

	it( 'fails with a blocking reason when the store currency is unsupported', () => {
		getSetting.mockReturnValue( { code: 'BRL' } );

		const check = build( [ 'USD' ] );

		expect( check.status ).toBe( STATUS.FAIL );
		expect( check.blockingText ).toEqual( expect.any( String ) );
		expect( check.blockingText ).not.toBe( '' );
	} );
} );

describe( 'buildCardMethodCheck', () => {
	it( 'passes when the card method is enabled and fails otherwise', () => {
		expect(
			buildCardMethodCheck( { isCardEnabled: true, methodLabel: 'Link' } )
				.status
		).toBe( STATUS.PASS );

		const failed = buildCardMethodCheck( {
			isCardEnabled: false,
			methodLabel: 'Link',
		} );
		expect( failed.status ).toBe( STATUS.FAIL );
		expect( failed.blockingText ).not.toBe( '' );
	} );
} );

describe( 'buildLocations', () => {
	it( 'maps keys to labeled rows with the enabled state of each toggle', () => {
		expect(
			buildLocations( [ 'checkout', 'product', 'cart' ], [ 'product' ] )
		).toEqual( [
			{ key: 'checkout', label: 'Checkout', enabled: false },
			{ key: 'product', label: 'Product page', enabled: true },
			{ key: 'cart', label: 'Cart', enabled: false },
		] );
	} );
} );
