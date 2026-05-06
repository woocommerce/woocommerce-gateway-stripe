import { getStripeDevWidgetOptions } from '../get-stripe-dev-widget-options';
import { getStripeServerData } from '../utils';

jest.mock( '../utils', () => ( {
	getStripeServerData: jest.fn(),
} ) );

const DISABLED_OPTIONS = {
	developerTools: {
		assistant: {
			enabled: false,
		},
	},
};

const ENABLED_OPTIONS = {
	developerTools: {
		assistant: {
			enabled: true,
		},
	},
};

describe( 'getStripeDevWidgetOptions', () => {
	beforeEach( () => {
		getStripeServerData.mockReset();
	} );

	it( 'returns disabled options when server data is null', () => {
		getStripeServerData.mockReturnValue( null );

		expect( getStripeDevWidgetOptions() ).toEqual( DISABLED_OPTIONS );
	} );

	it( 'returns disabled options when showStripeDeveloperWidget is absent', () => {
		getStripeServerData.mockReturnValue( {} );

		expect( getStripeDevWidgetOptions() ).toEqual( DISABLED_OPTIONS );
	} );

	it( 'returns disabled options when showStripeDeveloperWidget is false', () => {
		getStripeServerData.mockReturnValue( {
			showStripeDeveloperWidget: false,
		} );

		expect( getStripeDevWidgetOptions() ).toEqual( DISABLED_OPTIONS );
	} );

	it( 'returns enabled options when showStripeDeveloperWidget is true', () => {
		getStripeServerData.mockReturnValue( {
			showStripeDeveloperWidget: true,
		} );

		expect( getStripeDevWidgetOptions() ).toEqual( ENABLED_OPTIONS );
	} );
} );
