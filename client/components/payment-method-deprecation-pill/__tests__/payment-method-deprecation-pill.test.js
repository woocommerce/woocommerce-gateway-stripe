import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodDeprecationPill from '..';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

describe( 'PaymentMethodDeprecationPill', () => {
	it( 'should render', () => {
		render(
			<UpeToggleContext.Provider>
				<PaymentMethodDeprecationPill />
			</UpeToggleContext.Provider>
		);

		expect( screen.queryByText( 'Deprecated' ) ).toBeInTheDocument();
	} );
} );
