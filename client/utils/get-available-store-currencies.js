/**
 * Returns the currencies that the store may use at checkout.
 *
 * The localized list includes currencies supplied by multi-currency plugins.
 * The WooCommerce base currency keeps older or non-admin contexts working.
 *
 * @param {string|null} storeCurrencyCode WooCommerce base currency code.
 * @return {string[]} Available store currency codes.
 */
const getAvailableStoreCurrencies = ( storeCurrencyCode = null ) => {
	const availableStoreCurrencies =
		window?.wc_stripe_settings_params?.available_store_currencies;

	if ( Array.isArray( availableStoreCurrencies ) ) {
		return availableStoreCurrencies;
	}

	return storeCurrencyCode ? [ storeCurrencyCode ] : [];
};

export default getAvailableStoreCurrencies;
