/**
 * External dependencies
 */
import React from 'react';
import styled from '@emotion/styled';

const PillWrapper = styled.span`
	border: 1px solid #757575;
	border-radius: 28px;
	color: #757575;
	display: inline-block;
	font-size: 12px;
	font-weight: 400;
	line-height: 1.4em;
	padding: 2px 8px;
	width: fit-content;
 `;

const Pill = ({ ...restProps }) => (
	<PillWrapper {...restProps} />
);

export default Pill;
