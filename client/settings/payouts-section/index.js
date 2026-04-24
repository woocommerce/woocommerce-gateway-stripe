import React from 'react';
import styled from '@emotion/styled';
import BalanceCard from './balance-card';
import PayoutsTable from './payouts-table';

const Wrapper = styled.div`
	display: flex;
	flex-direction: column;
	gap: 24px;
`;

const PayoutsPanel = () => (
	<Wrapper>
		<BalanceCard />
		<PayoutsTable />
	</Wrapper>
);

export default PayoutsPanel;
