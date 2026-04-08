/* global wc_stripe_settings_params */
import React from 'react';
import GridIcon from 'gridicons';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import './style.scss';

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

const OptimizedCheckoutFirstMethodNotice = () => {
	const showStripeFirstMethodNotice =
		wc_stripe_settings_params.show_stripe_first_method_notice; // eslint-disable-line camelcase

	if ( ! showStripeFirstMethodNotice ) {
		return null;
	}

	const handleAction = () => {
		// eslint-disable-next-line no-console
		console.log( 'handleAction' );
	};

	const handleRemove = () => {
		// eslint-disable-next-line no-console
		console.log( 'handleRemove' );
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
