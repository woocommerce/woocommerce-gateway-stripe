import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import { RadioControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './style.scss';

const makeButtonSizeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			helpText: (
				<span className="express-checkout-customize__option-muted-text" />
			),
		},
	} );

const buttonSizeOptions = [
	{
		label: makeButtonSizeText(
			__(
				'Small {{helpText}}(40 px){{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'small',
	},
	{
		label: makeButtonSizeText(
			__(
				'Default {{helpText}}(48 px){{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'default',
	},
	{
		label: makeButtonSizeText(
			__(
				'Large {{helpText}}(56 px){{/helpText}}',
				'woocommerce-gateway-stripe'
			)
		),
		value: 'large',
	},
];

/**
 * The button "Size" radio control shared by every Customize express checkouts tab. The size value
 * is owned by the caller's settings store. The section is responsible for the surrounding
 * "Appearance" heading so it can add method-specific controls (e.g. Theme on the Express tab).
 *
 * @param {Object}   props
 * @param {string}   props.size     The selected size key (`small`/`default`/`large`).
 * @param {Function} props.onChange Receives the next size key.
 */
const ExpressCheckoutButtonSizeControl = ( { size, onChange } ) => (
	<RadioControl
		help={ __(
			'Note that larger buttons are more suitable for mobile use.',
			'woocommerce-gateway-stripe'
		) }
		label={ __( 'Size', 'woocommerce-gateway-stripe' ) }
		selected={ size }
		options={ buttonSizeOptions }
		onChange={ onChange }
	/>
);

export default ExpressCheckoutButtonSizeControl;
