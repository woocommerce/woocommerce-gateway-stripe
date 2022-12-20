const { test, expect } = require( '@playwright/test' );

test.describe.skip(
	`Customer can checkout with a normal credit card @smoke`,
	() => {
		test( 'customer can add products to the cart and go to the checkout', async () => {} );
		test( 'customer can checkout with a normal credit card', async () => {} );
	}
);
