import { dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { getSettings } from '../resolvers';
import { updateSettings } from '../actions';

jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/data-controls' );

describe( 'Settings resolvers tests', () => {
	describe( 'getSettings()', () => {
		/**
		 * Iterates through a generator and if a key matching
		 * last yielded value is present in valuesToSend,
		 * its value will be sent to the next generator.next() call.
		 *
		 * This is meant to simulate middleware that transform
		 * yielded values into other values sent to the generator.
		 *
		 * @param {Function} generator Generator to iterate through.
		 * @param {Object} valuesToSend Mapping of yielded value to value sent to generator.next() on next call.
		 * @return {*[]} Array of all yielded values.
		 */
		const iterateAndSendToNext = ( generator, valuesToSend ) => {
			const allYieldedValues = [];
			let lastYielded = { value: undefined, done: false };

			while ( ! lastYielded.done ) {
				const valueToSend = valuesToSend[ lastYielded.value ];
				lastYielded = generator.next( valueToSend );
				allYieldedValues.push( lastYielded.value );
			}

			return allYieldedValues;
		};

		beforeEach( () => {
			const noticesDispatch = {
				createErrorNotice: jest.fn(),
			};

			apiFetch.mockImplementation( () => {} );
			dispatch.mockImplementation( ( storeName ) => {
				if ( storeName === 'core/notices' ) {
					return noticesDispatch;
				}

				return {};
			} );
		} );

		test( 'makes expected GET request', () => {
			apiFetch.mockReturnValue( 'api request' );

			const yielded = [ ...getSettings() ];

			const expectedAction = {
				path: '/wc/v3/wc_stripe/settings',
			};

			expect( apiFetch ).toHaveBeenCalledWith( expectedAction );
			expect( yielded ).toContainEqual( 'api request' );
		} );

		test( 'updates settings with received results', () => {
			apiFetch.mockReturnValue( 'apiFetch() result' );

			const yielded = iterateAndSendToNext( getSettings(), {
				'apiFetch() result': { foo: 'bar' },
			} );

			expect( yielded ).toContainEqual(
				updateSettings( { foo: 'bar' } )
			);
		} );

		test( 'if an error is thrown, displays an error notice', () => {
			const getSettingsGenerator = getSettings();

			apiFetch.mockImplementation( () => {
				getSettingsGenerator.throw( 'Some error' );
			} );

			// eslint-disable-next-line no-unused-expressions
			[ ...getSettingsGenerator ];

			expect(
				dispatch( 'core/notices' ).createErrorNotice
			).toHaveBeenCalledWith( 'Error retrieving settings.' );
		} );
	} );
} );
