/**
 * Handles the confirmation of card payments (3DSv2 modals/SCA challenge).
 *
 * @param {Object}   api               The API used for connection both with the server and Stripe.
 * @param {Object}   paymentDetails    Details about the payment, received from the server.
 * @param {Object}   emitResponse      Various helpers for usage with observer response objects.
 * @param {boolean}  shouldSavePayment Indicates whether the payment method should be saved or not.
 * @return {Object}                An object, which contains the result from the action.
 */
export default async function confirmCardPayment(
	api,
	paymentDetails,
	emitResponse,
	shouldSavePayment
) {
	const { redirect, payment_method: paymentMethod } = paymentDetails;

	try {
		const confirmation = api.confirmIntent(
			redirect,
			shouldSavePayment ? paymentMethod : null
		);

		// `true` means there is no intent to confirm.
		if ( confirmation === true ) {
			return {
				type: 'success',
				redirectUrl: redirect,
			};
		}

		// `confirmIntent` also returns `isOrderPage`, but that's not supported in blocks yet.
		const { request } = confirmation;

		const finalRedirect = await request;
		return {
			type: 'success',
			redirectUrl: finalRedirect,
		};
	} catch ( error ) {
		return {
			type: 'error',
			message: error.message,
			messageContext: emitResponse.noticeContexts.PAYMENTS,
		};
	}
}
