import React from 'react';
import { render } from '@testing-library/react';
import OptimizedCheckoutFirstMethodNotice from 'wcstripe/settings/advanced-settings-section/optimized-checkout-first-method-notice';

jest.mock( '@wordpress/components', () => ( {
	Notice: ( { children } ) => <div>{ children }</div>,
} ) );

jest.mock( 'wcstripe/utils', () => ( {
	dismissNotice: jest.fn(),
	moveStripeToTop: jest.fn(),
} ) );

describe( 'OptimizedCheckoutFirstMethodNotice', () => {
	let prevParams;

	beforeEach( () => {
		prevParams = global.wc_stripe_settings_params;
		global.wc_stripe_settings_params = {
			...prevParams,
			show_stripe_first_method_notice: true,
		};
	} );

	afterEach( () => {
		global.wc_stripe_settings_params = prevParams;
	} );

	it.each( [
		[
			'OC off',
			{ isOCEnabled: false, show_stripe_first_method_notice: true },
		],
		[
			'notice suppressed',
			{ isOCEnabled: true, show_stripe_first_method_notice: false },
		],
	] )( 'renders nothing when %s', ( _label, params ) => {
		global.wc_stripe_settings_params = { ...prevParams, ...params };

		const { container } = render(
			<OptimizedCheckoutFirstMethodNotice
				isOCEnabled={ params.isOCEnabled }
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'shows the notice when OC is enabled and the notice is not dismissed', () => {
		const { container } = render(
			<OptimizedCheckoutFirstMethodNotice isOCEnabled={ true } />
		);

		expect( container.firstChild ).toBeDefined();
	} );
} );
