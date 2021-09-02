/**
 * External dependencies
 */
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import '../account-status/shared.scss';

const DepositsStatus = ( props ) => {
	const { depositsStatus, iconSize } = props;
	let className = 'account-status__info__green';
	let description;
	let icon = <Icon icon="dashicons-yes-alt" size={ iconSize } />;

	if ( depositsStatus === 'disabled' ) {
		description = __( 'Disabled', 'woocommerce-gateway-stripe' );
		className = 'account-status__info__red';
		icon = <Icon icon="dashicons-warning" size={ iconSize } />;
	} else if ( depositsStatus === 'daily' ) {
		description = __( 'Daily', 'woocommerce-gateway-stripe' );
	} else if ( depositsStatus === 'weekly' ) {
		description = __( 'Weekly', 'woocommerce-gateway-stripe' );
	} else if ( depositsStatus === 'monthly' ) {
		description = __( 'Monthly', 'woocommerce-gateway-stripe' );
	} else if ( depositsStatus === 'manual' ) {
		const learnMoreHref =
			'https://docs.woocommerce.com/document/payments/faq/deposits-suspended/';
		description = createInterpolateElement(
			/* translators: <a> - suspended accounts FAQ URL */
			__(
				'Temporarily suspended (<a>learn more</a>)',
				'woocommerce-gateway-stripe'
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href={ learnMoreHref }
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		);
		className = 'account-status__info__yellow';
		icon = <Icon icon="dashicons-warning" size={ iconSize } />;
	} else {
		description = __( 'Unknown', 'woocommerce-gateway-stripe' );
	}

	return (
		<span className={ className }>
			{ icon }
			{ description }
		</span>
	);
};

export default DepositsStatus;
