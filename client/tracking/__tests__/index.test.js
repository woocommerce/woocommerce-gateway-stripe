import { recordEvent } from '..';

jest.mock( '@wordpress/dom-ready', () => ( cb ) => cb() );

describe( 'tracking', () => {
	beforeEach( () => {
		global.wcTracks = undefined;

		global.wc_stripe_settings_params = {
			is_test_mode: 'yes',
			plugin_version: '1.2.3',
		};
	} );

	it( 'does not fail if the global library is not present in the DOM', () => {
		expect( () =>
			recordEvent( 'event_name', { value: '1' } )
		).not.toThrow();
	} );

	it( 'does not track if tracking is not enabled', () => {
		const recordEventMock = jest.fn();
		global.wcTracks = { recordEvent: recordEventMock, isEnabled: false };

		recordEvent( 'event_name', { value: '1' } );

		expect( recordEventMock ).not.toHaveBeenCalled();
	} );
	it( 'does tracks the event with its payload', () => {
		const recordEventMock = jest.fn();
		global.wcTracks = { recordEvent: recordEventMock, isEnabled: true };

		recordEvent( 'event_name', { value: '1' } );

		expect( recordEventMock ).toHaveBeenCalledWith( 'event_name', {
			value: '1',
			is_test_mode: 'yes',
			stripe_version: '1.2.3',
		} );
	} );
} );
