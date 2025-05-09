import React from 'react';
import styled from '@emotion/styled';
import icon from './icon.svg';

const IconWrapper = styled.img`
	height: 12px;
	width: 12px;
`;

const RecurringPaymentIcon = () => <IconWrapper src={ icon } alt="" />;

export default RecurringPaymentIcon;
