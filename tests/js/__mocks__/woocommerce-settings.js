// Mock for @woocommerce/settings, a webpack external not installed as a
// devDependency. At runtime the import resolves to the `wc.wcSettings` API,
// which serves data from the separate `wcSettings` global printed by PHP.
// This mock does the same — tests inject data via `global.wcSettings`.
// Keep it faithful to the real implementation in the WooCommerce monorepo:
// plugins/woocommerce/client/blocks/assets/js/settings/shared/
const getSetting = (
	name,
	fallback = false,
	filter = ( val, fb ) => ( typeof val !== 'undefined' ? val : fb )
) => {
	const settings = global.wcSettings || {};
	let value = fallback;

	if ( name in settings ) {
		value = settings[ name ];
	} else if ( name.includes( '_data' ) ) {
		// Mirrors the runtime back-compat lookup: `<method>_data` settings
		// (e.g. `stripe_data`) are served from the dedicated
		// `paymentMethodData` setting.
		const nameWithoutData = name.replace( '_data', '' );
		const paymentMethodData = getSetting( 'paymentMethodData', {} );
		value =
			nameWithoutData in paymentMethodData
				? paymentMethodData[ nameWithoutData ]
				: fallback;
	}

	return filter( value, fallback );
};

const getAdminLink = ( path ) => getSetting( 'adminUrl' ) + path;

const ADMIN_URL = ( global.wcSettings || {} ).adminUrl;

module.exports = { getSetting, getAdminLink, ADMIN_URL };
