import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { isEeaCountry } from 'wcstripe/utils/eea-countries';
import './styles.scss';

/**
 * Adaptive pricing disclosure for EEA customers.
 *
 * @param {Object} props                Component props.
 * @param {string} props.billingCountry The billing country.
 */
export function AdaptivePricingDisclosure( { billingCountry = '' } ) {
	const [ detailsExpanded, setDetailsExpanded ] = useState( false );

	if ( ! isEeaCountry( billingCountry ) ) {
		return null;
	}

	const showLabel = __( 'Show details', 'woocommerce-gateway-stripe' );
	const hideLabel = __( 'Hide details', 'woocommerce-gateway-stripe' );

	return (
		<div className="wc-stripe-adaptive-pricing-disclosure">
			<p
				className={ `wc-stripe-adaptive-pricing-disclosure__summary${
					detailsExpanded ? '' : '--hidden'
				}` }
			>
				<span className="wc-stripe-adaptive-pricing-disclosure__rate">
					{
						/* translators: %s: percentage value for the conversion service fee. */
						__(
							'(Includes X% conversion service).',
							'woocommerce-gateway-stripe'
						)
					}
				</span>
				<button
					type="button"
					className="wc-stripe-adaptive-pricing-disclosure__toggle"
					aria-expanded={ detailsExpanded }
					onClick={ () => setDetailsExpanded( ( v ) => ! v ) }
				>
					{ detailsExpanded ? hideLabel : showLabel }
				</button>
			</p>
			{ detailsExpanded && (
				<p className="wc-stripe-adaptive-pricing-disclosure__details">
					{
						/* translators: %s: percentage markup over the ECB reference rate. */
						__(
							'This rate is X% over the European Central Bank reference rate and guarantees the exchange rate during checkout.',
							'woocommerce-gateway-stripe'
						)
					}
				</p>
			) }
		</div>
	);
}
