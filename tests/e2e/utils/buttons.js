export const buttonsUtils = {
	clickButtonWithText: async ( text, parent = '' ) => {
		const selector =
			parent +
			'//*[text() = "' +
			text +
			'"]|//*[@aria-label="' +
			text +
			'"]';

		await page.waitForXPath( selector, {
			visible: true,
		} );

		const [ button ] = await page.$x( selector );

		if ( ! button ) {
			throw new Error( 'Button not found' );
		}

		await button.click();
	},
};
