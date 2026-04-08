import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import OptimizedCheckoutFirstMethodNotice from 'wcstripe/settings/advanced-settings-section/optimized-checkout-first-method-notice';
import { dismissNotice, moveStripeToTop } from 'wcstripe/utils';

jest.mock( '@wordpress/components', () => ( {
	Notice: ( { children, actions, onRemove } ) => (
		<div>
			{ children }
			{ actions?.map( ( action, index ) => (
				<button key={ index } type="button" onClick={ action.onClick }>
					{ action.label }
				</button>
			) ) }
			{ onRemove ? (
				<button
					type="button"
					aria-label="Dismiss the notice"
					onClick={ onRemove }
				/>
			) : null }
		</div>
	),
} ) );

jest.mock( 'wcstripe/utils', () => ( {
	dismissNotice: jest.fn(),
	moveStripeToTop: jest.fn(),
} ) );

describe( 'OptimizedCheckoutFirstMethodNotice', () => {
	const noticeCopy =
		'Optimized Checkout works best when Stripe is your first payment method. Move it to the top to start optimizing for conversions.';

	let prevParams;

	beforeEach( () => {
		prevParams = global.wc_stripe_settings_params;
		global.wc_stripe_settings_params = {
			...prevParams,
			show_stripe_first_method_notice: true,
		};
		dismissNotice.mockImplementation( ( _key, callback ) => {
			callback?.();
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
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
		render( <OptimizedCheckoutFirstMethodNotice isOCEnabled={ true } /> );

		expect( screen.getByText( noticeCopy ) ).toBeInTheDocument();
	} );

	it( 'calls moveStripeToTop and hides the notice when "Move to top" is clicked', async () => {
		render( <OptimizedCheckoutFirstMethodNotice isOCEnabled={ true } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Move to top' } )
		);

		expect( moveStripeToTop ).toHaveBeenCalled();
		expect( screen.queryByText( noticeCopy ) ).not.toBeInTheDocument();
	} );

	it( 'calls dismissNotice and hides the notice when the notice is dismissed', async () => {
		render( <OptimizedCheckoutFirstMethodNotice isOCEnabled={ true } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Dismiss the notice' } )
		);

		expect( dismissNotice ).toHaveBeenCalledWith(
			'wc_stripe_show_stripe_first_method_notice',
			expect.any( Function )
		);
		expect( screen.queryByText( noticeCopy ) ).not.toBeInTheDocument();
	} );
} );
