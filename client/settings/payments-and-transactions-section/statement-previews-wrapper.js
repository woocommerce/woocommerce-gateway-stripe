import React from 'react';
import styled from '@emotion/styled';

const StatementPreviews = styled.div`
	display: grid;
	grid-template-columns: 1fr 1fr;
	grid-column-gap: 15px;
	padding-bottom: 8px;

	> p {
		grid-column: 1/-1;
		margin-bottom: 8px;
	}

	@media screen and ( max-width: 609px ) {
		grid-template-columns: 1fr;
	}

	@media screen and ( min-width: 800px ) and ( max-width: 1109px ) {
		grid-template-columns: 1fr;
	}
`;

const StatementPreviewsWrapper = ( { children } ) => (
	<StatementPreviews>
		<p>Preview</p>
		{ children }
	</StatementPreviews>
);

export default StatementPreviewsWrapper;
