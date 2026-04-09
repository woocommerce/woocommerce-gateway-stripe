/* global wc_stripe_settings_params */
import React, { useState } from 'react';
import GridIcon from 'gridicons';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import { dismissNotice, moveStripeToTop } from 'wcstripe/utils';
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

const OptimizedCheckoutFirstMethodNotice = ( { isOCEnabled } ) => {
	const [ showNotice, setShowNotice ] = useState(
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params?.show_stripe_first_method_notice
	);

	if ( ! showNotice || ! isOCEnabled ) {
		return null;
	}

	const handleAction = () => {
		moveStripeToTop();
		setShowNotice( false );
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
