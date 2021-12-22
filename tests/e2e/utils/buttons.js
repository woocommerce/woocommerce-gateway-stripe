export const buttonsUtils = {
	/**
	 * Clicks on a button with a given text
	 * @param text button text
	 * @param parent when provided will only click on a button inside the given parent. Otherwise will click on the first button with the given name
	 */
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

	/**
	 * Clicks on a checkbox to change the state
	 * @param checkboxSelector XPath
	 */
	toggleCheckbox: async ( checkboxSelector ) => {
		const [ checkbox ] = await page.$x( checkboxSelector );

		if ( ! checkbox ) {
			throw new Error( 'Checkbox not found' );
		}

		await checkbox.click();
	},
};
