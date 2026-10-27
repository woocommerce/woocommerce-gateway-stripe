/**
 * DRAFT entry for the experimental WooCommerce payment settings screen.
 *
 * Draft starting point. It registers the Stripe page (one core-data entity)
 * and its fields, the same way the WooPayments PR does
 * (Automattic/woocommerce-payments#12269). PHP hands the config in through
 * `window.wcStripeSettingsScreen`; see includes/admin/class-wc-stripe-settings-screen.php.
 *
 * Not wired into webpack or built yet. See docs/payment-settings-screen.md before adding it: it needs
 * `registerEntityField` from a `@wordpress/editor` new enough to export it (Gutenberg 24.2+).
 */

/**
 * External dependencies
 */
import { dispatch } from '@wordpress/data';
import { registerEntityField } from '@wordpress/editor';

const config = window.wcStripeSettingsScreen;

if ( config ) {
	const { kind, name, baseURL, fields } = config;

	// WooCommerce renders a page per entity of this kind, so the entity is added before the
	// screen reads it. The entity name is the page segment and the record id (see PHP).
	dispatch( 'core' ).addEntities( [
		{
			kind,
			name,
			baseURL,
			key: 'id',
			label: name,
			getTitle: () => name,
		},
	] );

	// Each section owner can replace a field's plain control with a custom one here, by merging
	// a React `Edit` component into the field before registering it. See sections/README.md.
	fields.forEach( ( field ) => {
		registerEntityField( kind, name, field );
	} );
}
