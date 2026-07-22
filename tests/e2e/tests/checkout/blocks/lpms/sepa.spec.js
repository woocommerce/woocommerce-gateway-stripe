import { test } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user } from '../../../../utils';

const {
	clickPlaceOrder,
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	setupEuroLPMCheckout,
	fillSepaDetails,
	waitForOrderReceivedPage,
} = payments;

test.describe( 'SEPA Direct Debit payment tests @blocks @sepa', () => {
	let username, userEmail;

	test.beforeAll( async () => {
		// Create test user.
		const randomString = randomUUID();
		userEmail = randomString + '+' + config.get( 'users.customer.email' );
		username = randomString + '.' + config.get( 'users.customer.username' );

		const testUser = {
			...config.get( 'users.customer' ),
			...config.get( 'addresses.customer_netherlands' ),
			email: userEmail,
			username,
		};
		await api.create.customer( testUser );
	} );

	test.describe.configure( { mode: 'parallel' } );

	test( 'customer can pay with SEPA Direct Debit', async ( { page } ) => {
		await setupEuroLPMCheckout(
			page,
			'SEPA Direct Debit',
			config.get( 'addresses.customer_netherlands.billing' ),
			'blocks'
		);
		await fillSepaDetails( page, config.get( 'sepa.iban' ), 'blocks' );

		await clickPlaceOrder( page );
		await waitForOrderReceivedPage( page );
	} );

	test( 'customer can save and reuse SEPA Direct Debit', async ( {
		page,
	} ) => {
		// First order - Save the payment method.
		await test.step( 'Save payment method during first checkout', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
			await setupEuroLPMCheckout(
				page,
				'SEPA Direct Debit',
				config.get( 'addresses.customer_netherlands.billing' ),
				'blocks'
			);
			await fillSepaDetails( page, config.get( 'sepa.iban' ), 'blocks' );

			await page
				.locator(
					'.wc-block-components-payment-methods__save-card-info'
				)
				.click();

			await clickPlaceOrder( page );
			await waitForOrderReceivedPage( page );
		} );

		// Second order - Use saved payment method.
		await test.step( 'Use saved payment method for second checkout', async () => {
			await emptyCart( page );
			await setupCart( page );
			await setupBlocksCheckout(
				page,
				config.get( 'addresses.customer_netherlands.billing' )
			);
			await page
				.locator( 'label' )
				.filter( { hasText: 'SEPA IBAN ending in' } )
				.click();

			await clickPlaceOrder( page );
			await waitForOrderReceivedPage( page );
		} );
	} );
} );
