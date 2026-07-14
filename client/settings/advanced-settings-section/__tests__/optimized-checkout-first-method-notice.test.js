import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { dispatch } from '@wordpress/data';
import OptimizedCheckoutFirstMethodNotice from 'wcstripe/settings/advanced-settings-section/optimized-checkout-first-method-notice';
import { dismissNotice, moveStripeToTop } from 'wcstripe/utils';

const mockCreateSuccessNotice = jest.fn();
const mockCreateErrorNotice = jest.fn();

jest.mock( '@wordpress/data', () => ( {
	__esModule: true,
	dispatch: jest.fn( () => ( {
		createSuccessNotice: mockCreateSuccessNotice,
		createErrorNotice: mockCreateErrorNotice,
	} ) ),
} ) );

jest.mock( '@woocommerce/settings', () => ( {
	getAdminLink: jest.fn(
		( path ) => `https://example.com/wp-admin/${ path }`
	),
} ) );

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
		let resolveMove;
		moveStripeToTop.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					resolveMove = resolve;
				} )
		);

		render( <OptimizedCheckoutFirstMethodNotice isOCEnabled={ true } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Move to top' } )
		);

		expect( moveStripeToTop ).toHaveBeenCalled();

		await act( async () => {
			resolveMove();
			await Promise.resolve();
		} );

		expect( screen.queryByText( noticeCopy ) ).not.toBeInTheDocument();

		await waitFor( () => {
			expect( mockCreateSuccessNotice ).toHaveBeenCalledTimes( 1 );
		} );

		expect( dispatch ).toHaveBeenCalledWith( 'core/notices' );
		expect( mockCreateSuccessNotice ).toHaveBeenCalledWith(
			'Stripe is now the first option in checkout.',
			expect.objectContaining( {
				id: 'wc_stripe_stripe_first_checkout_success',
				speak: false,
				actions: [
					{
						url: 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout',
						label: 'Review the payment method order',
						openInNewTab: true,
					},
				],
			} )
		);
		expect( mockCreateErrorNotice ).not.toHaveBeenCalled();
	} );

	it( 'does not dispatch success notice when refreshPage is true', async () => {
		const reloadMock = jest.fn();
		const locationDescriptor = Object.getOwnPropertyDescriptor(
			window,
			'location'
		);
		Object.defineProperty( window, 'location', {
			configurable: true,
			value: {
				...window.location,
				reload: reloadMock,
			},
		} );

		let resolveMove;
		moveStripeToTop.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					resolveMove = resolve;
				} )
		);

		try {
			render(
				<OptimizedCheckoutFirstMethodNotice
					isOCEnabled={ true }
					refreshPage={ true }
				/>
			);

			await userEvent.click(
				screen.getByRole( 'button', { name: 'Move to top' } )
			);

			await act( async () => {
				resolveMove();
				await Promise.resolve();
			} );

			expect( reloadMock ).toHaveBeenCalled();
			expect( mockCreateSuccessNotice ).not.toHaveBeenCalled();
		} finally {
			if ( locationDescriptor ) {
				Object.defineProperty( window, 'location', locationDescriptor );
			}
		}
	} );

	it( 'dispatches an error notice when moveStripeToTop rejects', async () => {
		moveStripeToTop.mockRejectedValueOnce( new Error( 'network' ) );

		render( <OptimizedCheckoutFirstMethodNotice isOCEnabled={ true } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Move to top' } )
		);

		await waitFor( () => {
			expect( mockCreateErrorNotice ).toHaveBeenCalledTimes( 1 );
		} );

		expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
			'Error moving Stripe to the top of the payment methods list.',
			expect.objectContaining( {
				id: 'wc_stripe_stripe_first_checkout_error',
				speak: false,
			} )
		);
		expect( mockCreateSuccessNotice ).not.toHaveBeenCalled();
		expect( screen.getByText( noticeCopy ) ).toBeInTheDocument();
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
