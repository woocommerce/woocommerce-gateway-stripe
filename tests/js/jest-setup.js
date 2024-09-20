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
	global.wc_stripe_express_checkout_params = {};
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
