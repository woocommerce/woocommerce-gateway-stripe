export const buttonsUtils = {
	clickButtonWithText: async ( text ) => {
		await page.waitForXPath( '//*[text() = "' + text + '"]', {
			visible: true,
		} );

		const [ button ] = await page.$x( '//*[text() = "' + text + '"]' );

		if ( ! button ) {
			throw new Error( 'Button not found' );
		}

		await button.click();
	},
};
