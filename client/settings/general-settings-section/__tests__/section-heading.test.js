import { render, screen } from '@testing-library/react';
import { expect } from '@playwright/test';
import SectionHeading from 'wcstripe/settings/general-settings-section/section-heading';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'SectionHeading', () => {
	const globalValues = global.wc_stripe_settings_params;

	beforeEach( () => {
		global.wc_stripe_settings_params = {
			...globalValues,
			is_oc_enabled: false,
		};
	} );

	afterEach( () => {
		global.wc_stripe_settings_params = globalValues;
	} );

	it( 'default display', () => {
		const { getByText, getByLabelText } = render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<SectionHeading isChangingDisplayOrder={ false } />
			</UpeToggleContext.Provider>
		);

		expect( getByText( 'Payment methods' ) ).toBeInTheDocument();
		expect( getByText( 'Change display order' ) ).toBeInTheDocument();
		expect( getByLabelText( 'Payment methods menu' ) ).toBeInTheDocument();
	} );

	it( 'is changing display order', () => {
		const { getByText } = render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<SectionHeading isChangingDisplayOrder={ true } />
			</UpeToggleContext.Provider>
		);

		expect( getByText( 'Payment methods' ) ).toBeInTheDocument();
		expect( getByText( 'Cancel' ) ).toBeInTheDocument();
		expect( getByText( 'Save display order' ) ).toBeInTheDocument();

		expect(
			screen.queryByText( 'Change display order' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Payment methods menu' )
		).not.toBeInTheDocument();
	} );

	it( 'OC is enabled', () => {
		global.wc_stripe_settings_params = {
			...globalValues,
			is_oc_enabled: true,
		};
		const { getByText, getByLabelText } = render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<SectionHeading isChangingDisplayOrder={ false } />
			</UpeToggleContext.Provider>
		);

		expect( getByText( 'Payment methods' ) ).toBeInTheDocument();
		expect( getByLabelText( 'Payment methods menu' ) ).toBeInTheDocument();

		expect(
			screen.queryByText( 'Change display order' )
		).not.toBeInTheDocument();
	} );
} );
