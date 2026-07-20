// Mock for @woocommerce/settings, which is a webpack external (resolved to the
// wc.wcSettings global at runtime) and no longer installed as a devDependency.
// The real implementation lives in the WooCommerce monorepo at
// plugins/woocommerce/client/blocks/assets/js/settings/shared/ and ships as
// WooCommerce core's `wc-settings` script; keep this mock faithful to it.
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
