import { DEFAULT_VIEW } from '../constants';
import fields from '../fields';

const byId = ( id ) => fields.find( ( field ) => field.id === id );

const intent = {
	id: 'pi_123',
	created: 1757406094,
	amount: 3789,
	currency: 'usd',
	status: 'succeeded',
	description: 'Test WP - Order 517',
	latest_charge: {
		billing_details: { name: 'J D' },
		payment_method_details: {
			type: 'card',
			card: { brand: 'visa', last4: '4242' },
		},
	},
};

describe( 'payment intent fields', () => {
	// DataViews defaults enableSorting to the field type's default, which is
	// true for every built-in type. The list endpoint has no sort parameter, so
	// a field that forgets this renders a control that silently does nothing.
	it.each( fields.map( ( field ) => [ field.id, field ] ) )(
		'%s disables sorting',
		( id, field ) => {
			expect( field.enableSorting ).toBe( false );
		}
	);

	it( 'exposes exactly the columns the default view lists', () => {
		expect( fields.map( ( field ) => field.id ).sort() ).toEqual(
			[ ...DEFAULT_VIEW.fields ].sort()
		);
	} );

	it( 'converts the Unix timestamp to a parseable date string', () => {
		const value = byId( 'created' ).getValue( { item: intent } );

		expect( Number.isNaN( Date.parse( value ) ) ).toBe( false );
		expect( value ).toBe( new Date( 1757406094000 ).toISOString() );
	} );

	it.each( [
		[ 'amount', 3789 ],
		[ 'status', 'succeeded' ],
		[ 'payment_method', 'card' ],
		[ 'customer', 'J D' ],
		[ 'description', 'Test WP - Order 517' ],
	] )( '%s reads its value from the intent', ( id, expected ) => {
		expect( byId( id ).getValue( { item: intent } ) ).toBe( expected );
	} );

	it.each( fields.map( ( field ) => [ field.id, field ] ) )(
		'%s tolerates an intent with every optional value missing',
		( id, field ) => {
			const sparse = {
				id: 'pi_empty',
				created: null,
				amount: null,
				currency: null,
				status: null,
				description: null,
				latest_charge: null,
			};

			expect( () => field.getValue( { item: sparse } ) ).not.toThrow();
		}
	);
} );
