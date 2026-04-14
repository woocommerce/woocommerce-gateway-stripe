/* global wc_stripe_settings_params */
import { getAdminLink } from '@woocommerce/settings';
import React, { useState } from 'react';
import GridIcon from 'gridicons';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { dismissNotice, moveStripeToTop } from 'wcstripe/utils';
import { dispatch } from '@wordpress/data';
import './style.scss';

const PAYMENT_METHODS_CHECKOUT_SETTINGS_PATH =
	'admin.php?page=wc-settings&tab=checkout';

const WarningIcon = () => {
	return (
		<span data-testid="warning-icon">
			<GridIcon
				icon="notice-outline"
				size={ 20 }
				style={ {
					fill: '#DFB085',
				} }
			/>
		</span>
	);
};

/**
 * The Optimized Checkout first method notice component.
 * This notice is displayed when the Optimized Checkout is enabled and Stripe is not the first available gateway,
 * to inform the user that Stripe should be moved to the top of the payment methods list to use Optimized Checkout.
 *
 * @param {Object}  props             - The component props.
 * @param {boolean} props.isOCEnabled - Whether the Optimized Checkout is enabled.
 * @param {boolean} props.refreshPage - Whether to refresh the page after the notice is dismissed.
 * @return {React.ReactNode} The Optimized Checkout first method notice component.
 */
const OptimizedCheckoutFirstMethodNotice = ( {
	isOCEnabled,
	refreshPage = false,
} ) => {
	const [ showNotice, setShowNotice ] = useState(
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params?.show_stripe_first_method_notice
	);

	if ( ! showNotice || ! isOCEnabled ) {
		return null;
	}

	const showSuccessNotice = () => {
		const paymentMethodsUrl = getAdminLink(
			PAYMENT_METHODS_CHECKOUT_SETTINGS_PATH
		);

		dispatch( 'core/notices' ).createSuccessNotice(
			__(
				'Stripe is now the first option in checkout.',
				'woocommerce-gateway-stripe'
			),
			{
				id: 'wc_stripe_stripe_first_checkout_success',
				actions: [
					{
						url: paymentMethodsUrl,
						label: __(
							'Review the payment method order',
							'woocommerce-gateway-stripe'
						),
						openInNewTab: true,
					},
				],
				speak: false,
			}
		);
	};

	const showErrorNotice = () => {
		dispatch( 'core/notices' ).createErrorNotice(
			__(
				'Error moving Stripe to the top of the payment methods list.',
				'woocommerce-gateway-stripe'
			),
			{
				id: 'wc_stripe_stripe_first_checkout_error',
				speak: false,
			}
		);
	};

	const handleAction = () => {
		moveStripeToTop()
			.then( () => {
				setShowNotice( false );

				// Refresh the page on the WooCommerce > Settings > Payments page.
				if ( refreshPage ) {
					window.location.reload();
				} else {
					showSuccessNotice();
				}
			} )
			.catch( () => {
				showErrorNotice();
			} );
	};

	const handleRemove = () => {
		dismissNotice( 'wc_stripe_show_stripe_first_method_notice', () => {
			setShowNotice( false );
		} );
	};

	const actions = [
		{
			label: __( 'Move to top', 'woocommerce-gateway-stripe' ),
			onClick: handleAction,
			className: 'notice-action',
		},
	];

	return (
		<Notice
			className="wc-stripe-optimized-checkout-first-method-notice"
			status="warning"
			isDismissible={ true }
			onRemove={ handleRemove }
			actions={ actions }
		>
			<div className="notice-content">
				<WarningIcon />
				<div>
					{ __(
						'Optimized Checkout works best when Stripe is your first payment method. Move it to the top to start optimizing for conversions.',
						'woocommerce-gateway-stripe'
					) }
				</div>
			</div>
		</Notice>
	);
};

export default OptimizedCheckoutFirstMethodNotice;
