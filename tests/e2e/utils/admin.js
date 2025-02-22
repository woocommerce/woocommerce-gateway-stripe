/**
 * Enable or disable ACH payment method in Stripe settings.
 * @param {Page} page Playwright page fixture.
 * @param {boolean} enable Whether to enable or disable ACH.
 */
export const toggleACHPaymentMethod = async ( page, enable = true ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
	);

	const checkbox = page.getByRole( 'checkbox', { name: 'ACH Direct Debit' } );
	const isChecked = await checkbox.isChecked();

	if ( ( enable && ! isChecked ) || ( ! enable && isChecked ) ) {
		await checkbox.click();
		if ( ! enable ) {
			await page.getByRole( 'button', { name: 'Remove' } ).click();
		}
		await page.click( 'text=Save changes' );
		await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
	}
};
