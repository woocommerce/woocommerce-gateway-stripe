import React from 'react';
import styled from '@emotion/styled';
import header from './header.svg';

const Image = styled.img`
	max-width: 100%;
	width: 100%;
`;

const StripeBanner = () => <Image src={ header } alt="" />;

export default StripeBanner;
