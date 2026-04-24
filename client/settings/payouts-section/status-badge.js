import React from 'react';
import styled from '@emotion/styled';
import { __ } from '@wordpress/i18n';

const STATUS_COLORS = {
	paid: { bg: '#e0f5e9', fg: '#006b3c' },
	pending: { bg: '#fff3d6', fg: '#8a6a00' },
	in_transit: { bg: '#e0eefd', fg: '#0061a0' },
	canceled: { bg: '#eeeeee', fg: '#555555' },
	failed: { bg: '#fde2e2', fg: '#a30000' },
};

const Badge = styled.span`
	display: inline-block;
	padding: 2px 8px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 500;
	line-height: 1.4;
	background: ${ ( props ) => props.bg };
	color: ${ ( props ) => props.fg };
`;

const STATUS_LABELS = {
	paid: () => __( 'Paid', 'woocommerce-gateway-stripe' ),
	pending: () => __( 'Pending', 'woocommerce-gateway-stripe' ),
	in_transit: () => __( 'In transit', 'woocommerce-gateway-stripe' ),
	canceled: () => __( 'Canceled', 'woocommerce-gateway-stripe' ),
	failed: () => __( 'Failed', 'woocommerce-gateway-stripe' ),
};

const StatusBadge = ( { status } ) => {
	const colors = STATUS_COLORS[ status ] || STATUS_COLORS.canceled;
	const label = STATUS_LABELS[ status ] ? STATUS_LABELS[ status ]() : status;

	return (
		<Badge bg={ colors.bg } fg={ colors.fg }>
			{ label }
		</Badge>
	);
};

export default StatusBadge;
