/**
 * Handles the confirmation of card payments (3DSv2 modals/SCA challenge).
 *
 * @param {Object}   api            The API used for connection both with the server and Stripe.
 * @param {string}   redirectUrl    The URL to redirect to after confirming the intent on Stripe.
 * @param {boolean}  paymentNeeded  A boolean whether a payment or a setup confirmation is needed.
 * @param {Object}   elements       Reference to the Stripe elements.
 * @param {Object}   billingData    An object containing the customer's billing data.
 * @param {Object}   emitResponse   Various helpers for usage with observer response objects.
 * @return {Object}                An object, which contains the result from the action.
 */
export const confirmUpePayment = async (
	api,
	redirectUrl,
	paymentNeeded,
	elements,
	billingData,
	emitResponse
) => {
	const name = `${ billingData.first_name } ${ billingData.last_name }`.trim();

	try {
		const confirmParams = {
			return_url: redirectUrl,
			payment_method_data: {
				billing_details: {
					name,
					email: billingData?.email,
					phone: billingData?.phone,
					address: {
						country: billingData?.country,
						postal_code: billingData?.postcode,
						state: billingData?.state,
						city: billingData?.city,
						line1: billingData?.address_1,
						line2: billingData?.address_2,
					},
				},
			},
		};

		if ( paymentNeeded ) {
			const { error } = await api.getStripe().confirmPayment( {
				elements,
				confirmParams,
			} );

			if ( error ) {
				throw error;
			}
		} else {
			const { error } = await api.getStripe().confirmSetup( {
				elements,
				confirmParams,
			} );

			if ( error ) {
				throw error;
			}
		}
	} catch ( error ) {
		return {
			type: 'error',
			message: error.message,
			messageContext: emitResponse.noticeContexts.PAYMENTS,
		};
	}
};
