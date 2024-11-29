/**
 * Populate order attribution inputs with order tracking data.
 *
 * @return {void}
 */
export const populateOrderAttributionInputs = () => {
	const orderAttribution = window?.wc_order_attribution;
	if ( orderAttribution ) {
		orderAttribution.setOrderTracking(
			orderAttribution.params.allowTracking
		);
	}
};
