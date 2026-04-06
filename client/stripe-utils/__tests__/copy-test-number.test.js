/**
 * Tests for the copy-to-clipboard handler in test mode instructions.
 */

let writeText;
let createInfoNotice;

beforeAll( () => {
	// Load the side-effect module once — it registers a document click listener.
	require( 'wcstripe/stripe-utils/copy-test-number' );
} );

beforeEach( () => {
	document.body.innerHTML = '';

	writeText = jest.fn( () => Promise.resolve() );
	Object.defineProperty( navigator, 'clipboard', {
		value: { writeText },
		writable: true,
		configurable: true,
	} );

	createInfoNotice = jest.fn();
	window.wp = {
		data: {
			dispatch: jest.fn( () => ( { createInfoNotice } ) ),
		},
	};
} );

afterEach( () => {
	delete window.wp;
} );

function createButton( number ) {
	document.body.innerHTML = `
		<button class="wc-stripe-copy-test-number">
			<span>${ number }</span>
			<i></i>
		</button>
	`;

	// jsdom doesn't implement innerText — define it from textContent.
	const span = document.querySelector( '.wc-stripe-copy-test-number span' );
	Object.defineProperty( span, 'innerText', {
		get() {
			return this.textContent;
		},
		configurable: true,
	} );

	return document.querySelector( '.wc-stripe-copy-test-number' );
}

function click( element ) {
	element.dispatchEvent( new Event( 'click', { bubbles: true } ) );
}

describe( 'copy-test-number', () => {
	it( 'copies the number with spaces stripped', () => {
		createButton( '4242 4242 4242 4242' );
		click( document.querySelector( '.wc-stripe-copy-test-number' ) );

		expect( writeText ).toHaveBeenCalledWith( '4242424242424242' );
	} );

	it( 'dispatches a snackbar notice on success', async () => {
		createButton( '4242424242424242' );
		click( document.querySelector( '.wc-stripe-copy-test-number' ) );

		// Flush the clipboard promise.
		await writeText.mock.results[ 0 ].value;

		expect( window.wp.data.dispatch ).toHaveBeenCalledWith(
			'core/notices'
		);
		expect( createInfoNotice ).toHaveBeenCalledWith(
			expect.any( String ),
			expect.objectContaining( {
				id: 'wc-stripe/test-number-copied',
				type: 'snackbar',
				context: 'wc/checkout/payments',
			} )
		);
	} );

	it( 'adds the success class on copy', async () => {
		const button = createButton( '4242424242424242' );
		click( button );

		// Flush the promise chain so the .then() callback runs.
		await new Promise( process.nextTick );

		expect( button.classList.contains( 'state--success' ) ).toBe( true );
	} );

	it( 'does nothing when clicking outside a copy button', () => {
		document.body.innerHTML = '<p>Some other content</p>';
		click( document.querySelector( 'p' ) );

		expect( writeText ).not.toHaveBeenCalled();
	} );

	it( 'does nothing when the span is empty', () => {
		createButton( '' );
		click( document.querySelector( '.wc-stripe-copy-test-number' ) );

		expect( writeText ).not.toHaveBeenCalled();
	} );

	it( 'does not throw when clipboard write fails', async () => {
		writeText.mockRejectedValueOnce( new Error( 'denied' ) );
		createButton( '4242424242424242' );

		click( document.querySelector( '.wc-stripe-copy-test-number' ) );

		// Flush the rejected promise — should not propagate.
		await expect(
			writeText.mock.results[ 0 ].value.catch( () => 'caught' )
		).resolves.toBe( 'caught' );
	} );

	it( 'does nothing when clipboard API is unavailable', () => {
		Object.defineProperty( navigator, 'clipboard', {
			value: undefined,
			writable: true,
			configurable: true,
		} );
		const button = createButton( '4242424242424242' );

		expect( () => click( button ) ).not.toThrow();
		expect( button.classList.contains( 'state--success' ) ).toBe( false );
	} );

	it( 'works when clicking a child element inside the button', () => {
		createButton( '4000056655665556' );
		const icon = document.querySelector( '.wc-stripe-copy-test-number i' );
		click( icon );

		expect( writeText ).toHaveBeenCalledWith( '4000056655665556' );
	} );
} );
