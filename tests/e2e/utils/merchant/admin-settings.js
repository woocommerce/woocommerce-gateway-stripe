const config = require( 'config' );
const baseUrl = config.get( 'url' );
const WP_ADMIN_PAGES = baseUrl + 'wp-admin/edit.php?post_type=page';

// Add a new page called "Checkout WCB"
export async function addBlocksCheckoutPage() {
	await page.goto( WP_ADMIN_PAGES, {
		waitUntil: 'networkidle0',
	} );

	await expect( page ).toClick( '.page-title-action', {
		waitUntil: 'networkidle0',
	} );
	await page.waitForSelector( '.editor-post-title__input' );
	await page.keyboard.press( 'Escape' ); // to dismiss a dialog if present
	await page.type( '.editor-post-title__input', 'Checkout WCB' );

	// Insert new checkout by WCB (searching for Checkout block and pressing Enter)
	await expect( page ).toClick(
		'button.edit-post-header-toolbar__inserter-toggle'
	);
	await expect( page ).toFill(
		'.block-editor-inserter__search-input',
		'Checkout'
	);
	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Enter' );

	// Dismiss dialog about potentially compatibility issues
	await page.keyboard.press( 'Escape' ); // to dismiss a dialog if present

	// Publish the page
	await expect( page ).toClick( 'button.editor-post-publish-panel__toggle' );
	await expect( page ).toClick( 'button.editor-post-publish-button' );
	await expect( page ).toMatch( 'Page published.' );
}
