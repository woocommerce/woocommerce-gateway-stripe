import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { admin, payments, api, user } from '../../../../utils';

test.describe( 'BLIK payment tests @shortcode', () => {
	let username, userEmail;

	test.beforeAll( async ( { browser } ) => {
		await test.step( 'Setup test environment', async () => {
			// Required for BLIK.
			try {
				await admin.updateStoreCurrency( browser, 'PLN' );
				console.log( 'Currency set to PLN' );
			} catch ( e ) {
				console.error( 'Failed to update store currency:', e );
				throw e;
			}

			// Create test user
			try {
				const randomString = randomUUID();
				userEmail =
					randomString + '+' + config.get( 'users.customer.email' );
				username =
					randomString +
					'.' +
					config.get( 'users.customer.username' );

				const testUser = {
					...config.get( 'users.customer' ),
					...config.get( 'addresses.customer' ),
					email: userEmail,
					username,
				};
				await api.create.customer( testUser );
				console.log( 'Customer created' );
			} catch ( e ) {
				console.error( 'Failed to create customer:', e );
				throw e;
			}
		} );

		// This ensures that we start the BLIK payment method from a known state.
		await test.step( 'Ensure BLIK starts enabled', async () => {
			try {
				await admin.togglePaymentMethod( browser, 'BLIK', true );
			} catch ( e ) {
				console.error( 'Failed to ensure BLIK is enabled:', e );
				throw e;
			}
		} );
	} );

	test( 'should be able to toggle BLIK payment method', async ( {
		browser,
	} ) => {
		try {
			await test.step( 'Disable BLIK payment method', async () => {
				try {
					await admin.togglePaymentMethod( browser, 'BLIK', false );
				} catch ( e ) {
					console.error( 'Failed to disable BLIK:', e );
					throw e;
				}
			} );

			await test.step( 'Re-enable BLIK payment method', async () => {
				try {
					await admin.togglePaymentMethod( browser, 'BLIK', true );
				} catch ( e ) {
					console.error( 'Failed to re-enable BLIK:', e );
					throw e;
				}
			} );
		} catch ( e ) {
			console.error( 'BLIK payment method toggle test failed:', e );
			throw e;
		}
	} );

	test.afterAll( async ( { browser } ) => {
		await test.step( 'Cleanup created customer', async () => {
			try {
				await api.deletePost.customer( userEmail );
				console.log( 'Customer deleted' );
			} catch ( e ) {
				console.error( 'Failed to delete customer:', e );
				throw e;
			}
		} );

		// Turn the BLIK payment method back to disabled
		await test.step( 'Cleanup toggled BLIK payment method', async () => {
			try {
				await admin.togglePaymentMethod( browser, 'BLIK', false );
				console.log( 'BLIK payment method disabled' );
			} catch ( e ) {
				console.error( 'Failed to ensure BLIK is enabled:', e );
				throw e;
			}
		} );
	} );
} );
