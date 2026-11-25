import { navigateTo } from 'wcstripe/utils/navigation';

describe( 'navigateTo', () => {
	// jsdom only implements same-document navigation, so a hash URL is the
	// one destination whose effect can be observed here.
	it( 'navigates the page to the given URL', () => {
		navigateTo( '#wc-stripe-confirm-pi-123' );

		expect( window.location.hash ).toBe( '#wc-stripe-confirm-pi-123' );
	} );
} );
