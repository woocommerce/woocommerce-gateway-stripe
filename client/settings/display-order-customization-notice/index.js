/* global wc_stripe_settings_params */
import { __ } from '@wordpress/i18n';
import styled from '@emotion/styled';
import React, { useContext, useState } from 'react';
import { Icon, Notice } from '@wordpress/components';
import { info } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import UpeToggleContext from '../upe-toggle/context';

const NoticeWrapper = styled( Notice )`
	border-left: none;
	margin: 0 0 24px 0;
	background: #f0f6fc;
`;

const NoticeContent = styled.div`
	display: inline-grid;
	grid-template-columns: auto auto auto;
	gap: 12px;

	> svg {
		fill: var( --wp-admin-theme-color );
	}
`;

const DisplayOrderCustomizationNotice = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const [ showNotice, setShowNotice ] = useState(
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params.show_customization_notice
	);

	const handleDismissNotice = () => {
		apiFetch( {
			path: '/wc/v3/wc_stripe/settings/notice',
			method: 'POST',
			data: { wc_stripe_show_customization_notice: 'no' },
		} ).finally( () => {
			setShowNotice( false );
		} );
	};

	if ( isUpeEnabled || ! showNotice ) {
		return null;
	}

	return (
		<NoticeWrapper isDismissible={ true } onRemove={ handleDismissNotice }>
			<NoticeContent>
				<Icon icon={ info } size={ 24 } />
				{ __(
					"Customize the display order of Stripe payment methods for customers at checkout. This customization occurs within the plugin and won't affect the order in relation to other installed payment providers.",
					'woocommerce-gateway-stripe'
				) }
			</NoticeContent>
		</NoticeWrapper>
	);
};

export default DisplayOrderCustomizationNotice;
