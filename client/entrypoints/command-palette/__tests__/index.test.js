jest.mock(
	'@wordpress/commands',
	() => ( {
		store: 'core/commands',
	} ),
	{ virtual: true }
);

jest.mock( '@wordpress/data', () => {
	const registerCommand = jest.fn();
	return {
		dispatch: jest.fn( () => ( { registerCommand } ) ),
	};
} );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

import { dispatch } from '@wordpress/data';
import { registerStripeCommands } from 'wcstripe/entrypoints/command-palette';

describe( 'command palette registration', () => {
	const originalLocation = window.location;
	const { registerCommand } = dispatch();

	beforeEach( () => {
		registerCommand.mockClear();
		dispatch.mockClear();
		dispatch.mockImplementation( () => ( { registerCommand } ) );
	} );

	afterEach( () => {
		window.location = originalLocation;
	} );

	it( 'registers every Stripe destination command', () => {
		registerStripeCommands();

		const registeredNames = registerCommand.mock.calls.map(
			( [ command ] ) => command.name
		);

		expect( registeredNames ).toEqual( [
			'woocommerce-gateway-stripe/settings',
			'woocommerce-gateway-stripe/payment-methods',
			'woocommerce-gateway-stripe/express-checkout',
			'woocommerce-gateway-stripe/amazon-pay',
		] );
	} );

	it( 'gives each command a Stripe label, the view category and a callback that navigates to the Stripe section', () => {
		registerStripeCommands();

		registerCommand.mock.calls.forEach( ( [ command ] ) => {
			expect( command.label ).toEqual(
				expect.stringContaining( 'Stripe' )
			);
			expect( command.category ).toBe( 'view' );

			delete window.location;
			window.location = { href: '' };
			command.callback( { close: jest.fn() } );

			expect( window.location.href ).toEqual(
				expect.stringContaining(
					'admin.php?page=wc-settings&tab=checkout&section=stripe'
				)
			);
		} );
	} );

	it( 'navigates to the command URL and closes the palette when run', () => {
		registerStripeCommands();

		const settingsCommand = registerCommand.mock.calls
			.map( ( [ command ] ) => command )
			.find(
				( command ) =>
					command.name === 'woocommerce-gateway-stripe/settings'
			);
		const close = jest.fn();

		delete window.location;
		window.location = { href: '' };

		settingsCommand.callback( { close } );

		expect( window.location.href ).toBe(
			'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
		);
		expect( close ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does nothing when the command palette store is unavailable', () => {
		dispatch.mockReturnValueOnce( undefined );

		expect( () => registerStripeCommands() ).not.toThrow();
		expect( registerCommand ).not.toHaveBeenCalled();
	} );
} );
