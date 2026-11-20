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

jest.mock( 'wcstripe/utils/navigation' );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

import { navigateTo } from 'wcstripe/utils/navigation';
import { dispatch } from '@wordpress/data';
import { registerStripeCommands } from 'wcstripe/entrypoints/command-palette';

describe( 'command palette registration', () => {
	const { registerCommand } = dispatch();

	beforeEach( () => {
		registerCommand.mockClear();
		navigateTo.mockClear();
		dispatch.mockClear();
		dispatch.mockImplementation( () => ( { registerCommand } ) );
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

			command.callback( { close: jest.fn() } );

			expect( navigateTo ).toHaveBeenLastCalledWith(
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

		settingsCommand.callback( { close } );

		expect( navigateTo ).toHaveBeenCalledWith(
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
