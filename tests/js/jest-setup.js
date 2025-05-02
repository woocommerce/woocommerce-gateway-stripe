import '@testing-library/jest-dom';
import nock from 'nock';

beforeAll( () => {
	// ensures that the tests don't use any real endpoints
	nock.disableNetConnect();
} );

afterAll( () => {
	nock.enableNetConnect();
} );

beforeEach( () => {
	if ( ! nock.isActive() ) {
		nock.activate();
	}
} );

afterEach( () => {
	global.__PAYMENT_METHOD_FEES_ENABLED = false;
	function cleanup() {
		jest.clearAllTimers();
		nock.cleanAll();
		nock.restore();
	}

	if ( nock.isDone() ) {
		cleanup();
		return;
	}

	const pendingMockedRequests = [ ...nock.pendingMocks() ];
	cleanup();

	const nockError = `A test case completed with some requests that have not been queried:\n\n ${ pendingMockedRequests.join(
		' | '
	) }`;

	throw new Error( nockError );
} );

jest.mock( '@wordpress/date', () => {
	const nop = () => null;
	const ident = ( x ) => x;
	return {
		add: nop,
		sub: nop,
		isBefore: nop,
		isAfter: nop,
		isSameDay: nop,
		isSameMinute: nop,
		format: ident,
		dateI18n: ident,
		getSettings: () => ( {
			timezone: { offset: 0, string: 'UTC', zone: 'UTC' },
		} ),
		setSettings: nop,
		__experimentalGetSettings: () => ( {
			offset: 0,
			string: 'UTC',
			zone: 'UTC',
		} ),
	};
} );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn( () => ( { panel: 'stripe' } ) ),
	updateQueryString: jest.fn(),
} ) );

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ) );
