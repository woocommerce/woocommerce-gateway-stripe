import { dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { getPaymentGateway } from '../resolvers';
import { updatePaymentGateway } from '../actions';

jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/data-controls' );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( { section: 'stripe_alipay' } ),
} ) );

describe( 'Payment gateway resolvers tests', () => {
	describe( 'getPaymentGateway()', () => {
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

			const yielded = [ ...getPaymentGateway() ];

			const expectedAction = {
				path: '/wc/v3/wc_stripe/payment-gateway/stripe_alipay',
			};

			expect( apiFetch ).toHaveBeenCalledWith( expectedAction );
			expect( yielded ).toContainEqual( 'api request' );
		} );

		test( 'updates payment gateway with received results', () => {
			apiFetch.mockReturnValue( 'apiFetch() result' );

			const yielded = iterateAndSendToNext( getPaymentGateway(), {
				'apiFetch() result': { foo: 'bar' },
			} );

			expect( yielded ).toContainEqual(
				updatePaymentGateway( { foo: 'bar' } )
			);
		} );

		test( 'if an error is thrown, displays an error notice', () => {
			const getPaymentGatewayGenerator = getPaymentGateway();

			apiFetch.mockImplementation( () => {
				getPaymentGatewayGenerator.throw( 'Some error' );
			} );

			// eslint-disable-next-line no-unused-expressions
			[ ...getPaymentGatewayGenerator ];

			expect(
				dispatch( 'core/notices' ).createErrorNotice
			).toHaveBeenCalledWith(
				'Error retrieving payment gateway settings.'
			);
		} );
	} );
} );
