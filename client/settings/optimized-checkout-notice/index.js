import { __ } from '@wordpress/i18n';
import styled from '@emotion/styled';
import React from 'react';
import { Icon, Notice } from '@wordpress/components';
import { info } from '@wordpress/icons';
import interpolateComponents from 'interpolate-components';

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
	if ( ! isOCEnabled ) {
		return null;
	}

	return (
		<NoticeWrapper isDismissible={ false }>
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
