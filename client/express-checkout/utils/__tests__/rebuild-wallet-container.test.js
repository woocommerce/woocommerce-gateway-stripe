import { rebuildWalletContainer } from '../rebuild-wallet-container';

describe( 'wallet wrapper replacement with a connected SDK host', () => {
	let container;
	let host;
	beforeEach( () => {
		document.body.innerHTML =
			'<div id="wallet" class="merchant-wallet"><div id="sdk-host"><iframe></iframe></div></div>';
		container = document.getElementById( 'wallet' );
		host = document.getElementById( 'sdk-host' );
	} );
	afterEach( () => {
		delete Element.prototype.moveBefore;
	} );
	it( 'replaces the public wrapper while preserving the SDK host and iframe', () => {
		Element.prototype.moveBefore = jest.fn( function ( node, before ) {
			this.insertBefore( node, before );
		} );
		const frame = host.firstElementChild;
		const replacement = rebuildWalletContainer( container, host );
		expect( replacement ).not.toBe( container );
		expect( container.isConnected ).toBe( false );
		expect( replacement.id ).toBe( 'wallet' );
		expect( replacement.className ).toBe( 'merchant-wallet' );
		expect( replacement.firstElementChild ).toBe( host );
		expect( host.firstElementChild ).toBe( frame );
	} );
	it( 'keeps the original DOM intact when atomic moves are unavailable', () => {
		expect( rebuildWalletContainer( container, host ) ).toBeNull();
		expect( container.firstElementChild ).toBe( host );
	} );
	it( 'does not pretend a detached SDK host still has a live browsing context', () => {
		Element.prototype.moveBefore = jest.fn();
		host.remove();
		expect( rebuildWalletContainer( container, host ) ).toBeNull();
		expect( Element.prototype.moveBefore ).not.toHaveBeenCalled();
	} );
	it( 'leaves the original wrapper intact if the browser rejects the move', () => {
		Element.prototype.moveBefore = jest.fn( () => {
			throw new DOMException(
				'Disconnected host',
				'HierarchyRequestError'
			);
		} );
		expect( rebuildWalletContainer( container, host ) ).toBeNull();
		expect( document.querySelectorAll( '#wallet' ) ).toHaveLength( 1 );
		expect( container.firstElementChild ).toBe( host );
	} );
} );
