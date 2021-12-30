/**
 * Handles the authorization modal for 3D/SCA cards
 * @param {string} cardType
 * @param {boolean} authorize true if should click on COMPLETE AUTHENTICATION and false if should click on FAIL AUTHENTICATION
 */
export async function confirmCardAuthentication(
	cardType = '3DS',
	authorize = true
) {
	const target = authorize
		? '#test-source-authorize-3ds'
		: '#test-source-fail-3ds';

	// Stripe card input also uses __privateStripeFrame as a prefix, so need to make sure we wait for an iframe that
	// appears at the top of the DOM.
	const frameHandle = await page.waitForSelector(
		'body>div>iframe[name^="__privateStripeFrame"]'
	);
	const stripeFrame = await frameHandle.contentFrame();
	const challengeFrameHandle = await stripeFrame.waitForSelector(
		'iframe#challengeFrame'
	);
	let challengeFrame = await challengeFrameHandle.contentFrame();
	// 3DS 1 cards have another iframe enclosing the authorize form
	if ( '3DS' === cardType.toUpperCase() ) {
		const acsFrameHandle = await challengeFrame.waitForSelector(
			'iframe[name="acsFrame"]'
		);
		challengeFrame = await acsFrameHandle.contentFrame();
	}
	const button = await challengeFrame.waitForSelector( target );
	// Need to wait for the CSS animations to complete.
	await page.waitFor( 500 );
	await button.click();
}
