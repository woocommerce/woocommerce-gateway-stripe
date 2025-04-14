import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { admin, payments, api, user } from '../../../../utils';

test.describe( 'BLIK payment tests @shortcode', () => {
	let username, userEmail;

	test.beforeAll( async ( { browser } ) => {
		await test.step( 'Setup test environment', async () => {
			// Required for BLIK
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
	} );

	test.afterAll( async () => {
		await test.step( 'Cleanup test environment', async () => {
			try {
				await api.deletePost.customer( userEmail );
				console.log( 'Customer deleted' );
			} catch ( e ) {
				console.error( 'Failed to delete customer:', e );
				throw e;
			}
		} );
	} );
} );
