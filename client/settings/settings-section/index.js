import React from 'react';
import styled from '@emotion/styled';

const SettingsSectionWrapper = styled.div`
	display: flex;
	flex-flow: column;
	margin-bottom: 24px;

	&:last-child {
		margin-bottom: 0;
	}

	@media ( min-width: 800px ) {
		flex-flow: row;
	}
`;

const DescriptionWrapper = styled.div`
	flex: 0 1 auto;
	margin-bottom: 24px;

	@media ( min-width: 800px ) {
		flex: 0 0 25%;
		margin: 0 32px 0 0;
	}

	h2 {
		font-size: 16px;
		line-height: 24px;
	}

	p {
		font-size: 13px;
		line-height: 17.89px;
		margin: 12px 0;
	}

	> :last-child {
		margin-bottom: 0;
	}
`;

const Controls = styled.div`
	flex: 1 1 auto;
`;

const SettingsSection = ( {
	Description = () => null,
	children,
	...restProps
} ) => (
	<SettingsSectionWrapper { ...restProps }>
		<DescriptionWrapper>
			<Description />
		</DescriptionWrapper>
		<Controls>{ children }</Controls>
	</SettingsSectionWrapper>
);

export default SettingsSection;
