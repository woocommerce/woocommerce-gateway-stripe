export const buttonsUtils = {
	clickButtonWithText: async ( text ) => {
		await page.waitForXPath(
			'//*[text() = "' + text + '"]|//*[@aria-label="' + text + '"]',
			{
				visible: true,
			}
		);

		const [ button ] = await page.$x(
			'//*[text() = "' + text + '"]|//*[@aria-label="' + text + '"]'
		);

		if ( ! button ) {
			throw new Error( 'Button not found' );
		}

		await button.click();
	},
};
