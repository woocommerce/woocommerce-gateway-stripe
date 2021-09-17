/**
 * External dependencies
 */
import React from 'react';
import styled from '@emotion/styled';

/**
 * Internal dependencies
 */
import icon from './icon.svg';

const Image = styled.img`
	max-width: 100%;
	width: 100%;
`;

const StripeBanner = () => <Image src={ icon } alt="" />;

export default StripeBanner;
