/* global wc_stripe_settings_params */
import styled from '@emotion/styled';
import React, { useState } from 'react';
import { info } from '@wordpress/icons';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { Icon, Notice } from '@wordpress/components';
import { dismissNotice } from 'wcstripe/utils';

const NoticeWrapper = styled( Notice )`
	margin: 0 0 24px 0;
`;

const NoticeContent = styled.div`
	display: inline-grid;
	grid-template-columns: auto auto auto;
	gap: 12px;

	> svg {
		fill: var( --wp-admin-theme-color );
	}
`;

const OptimizedCheckoutNotice = ( { isOCEnabled } ) => {
	const [ showNotice, setShowNotice ] = useState(
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params.show_optimized_checkout_notice
	);

	if ( ! isOCEnabled || ! showNotice ) {
		return null;
	}

	const handleDismissNotice = () => {
		dismissNotice( 'wc_stripe_show_optimized_checkout_notice', () => {
			setShowNotice( false );
		} );
	};

	return (
		<NoticeWrapper isDismissible={ true } onRemove={ handleDismissNotice }>
			<NoticeContent>
				<Icon icon={ info } size={ 24 } />
				<div>
					{ interpolateComponents( {
						mixedString: __(
							"You're using Stripe's Optimized Checkout Suite to dynamically display the most relevant payment methods you've enabled to each customer. {{docLink}}Learn more{{/docLink}}",
							'woocommerce-gateway-stripe'
						),
						components: {
							docLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="https://woocommerce.com/document/stripe/admin-experience/optimized-checkout-suite/"
								/>
							),
						},
					} ) }
				</div>
			</NoticeContent>
		</NoticeWrapper>
	);
};

export default OptimizedCheckoutNotice;
