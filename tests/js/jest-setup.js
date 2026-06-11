import '@testing-library/jest-dom';
import nock from 'nock';

const resetWindowLocation = () => {
	if ( typeof global.__resetWindowLocation === 'function' ) {
		global.__resetWindowLocation();
	}
};

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

	resetWindowLocation();
} );

afterEach( () => {
	global.__PAYMENT_METHOD_FEES_ENABLED = false;
	function cleanup() {
		jest.clearAllTimers();
		nock.cleanAll();
		nock.restore();
		resetWindowLocation();
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
