/**
 * Handles the confirmation of Bank Transfer payments.
 *
 * @param {Object}   api            The API used for connection both with the server and Stripe.
 * @param {string}   redirectUrl    The URL to redirect to after confirming the intent on Stripe.
 * @param {Object}   billingData    An object containing the customer's billing data.
 * @param {Object}   elements       Reference to the Stripe elements.
 * @param {Object}   emitResponse   Various helpers for usage with observer response objects.
 *
 * @return {Object}                An object, which contains the result from the action.
 */
const confirmBankTransferPayment = async (
	api,
	redirectUrl,
	billingData,
	elements,
	emitResponse
) => {
	try {
		// Note: No need to redirect to the URL, as Stripe handles it.
		const { error } = await api.getStripe().confirmPayment( {
			elements,
			confirmParams: {
				return_url: redirectUrl,
				payment_method_data: { billing_details: billingData },
			},
		} );

		if ( error ) {
			throw error;
		}
	} catch ( error ) {
		return {
			type: 'error',
			message: error.message,
			messageContext: emitResponse.noticeContexts.PAYMENTS,
		};
	}
};

export default confirmBankTransferPayment;
