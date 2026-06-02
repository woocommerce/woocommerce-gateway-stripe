import { render, screen, act, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaymentGatewaysConfirmation from '../payment-gateways-confirmation';

// Mock dependencies.
jest.mock( 'data', () => ( {
	useSettings: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn().mockReturnValue( [ [] ] ),
	useExpressCheckoutEnabledSettings: jest.fn().mockReturnValue( '' ),
} ) );

jest.mock( '@wordpress/api-fetch', () => jest.fn( () => Promise.resolve() ) );

// Minimal jQuery mock for the ajaxSend event delegation and DOM queries.
const jQueryHandlers = {};
const jQueryMock = ( selector ) => {
	const selectorStr = typeof selector === 'string' ? selector : '';
	return {
		on: ( event, handler ) => {
			if ( ! jQueryHandlers[ event ] ) {
				jQueryHandlers[ event ] = [];
			}
			jQueryHandlers[ event ].push( handler );
		},
		off: ( event ) => {
			delete jQueryHandlers[ event ];
		},
		trigger: () => {
			// no-op for toggle clicks in tests
		},
		length: selectorStr.includes( 'woocommerce-input-toggle--disabled' )
			? 0
			: 1,
		removeClass: jest.fn().mockReturnValue( {
			length: 0,
		} ),
	};
};
global.jQuery = jQueryMock;

// Helper to fire the ajaxSend event through our mock.
const triggerStripeDisable = () => {
	const request = { abort: jest.fn() };
	const settings = {
		url: 'http://example.com/wp-admin/admin-ajax.php',
		data: 'action=woocommerce_toggle_gateway_enabled&gateway_id=stripe',
	};

	act( () => {
		( jQueryHandlers.ajaxSend || [] ).forEach( ( handler ) =>
			handler( {}, request, settings )
		);
	} );
};

const mockSurveyParams = {
	exit_survey_last_shown: null,
	stripe_account_id: 'acct_test',
	wc_store_id: 'uuid-test',
	plugin_version: '10.5.3',
	wc_version: '9.9.0',
	wp_version: '6.7.2',
};

describe( 'PaymentGatewaysConfirmation — exit survey integration', () => {
	beforeEach( () => {
		global.woocommerce_admin = {
			ajax_url: 'http://example.com/wp-admin/admin-ajax.php',
		};
	} );

	afterEach( () => {
		delete global.woocommerce_admin;
		delete global.wcStripeExitSurveyParams;
		Object.keys( jQueryHandlers ).forEach(
			( k ) => delete jQueryHandlers[ k ]
		);
	} );

	it( 'shows exit survey after confirming disable when cooldown is inactive', async () => {
		global.wcStripeExitSurveyParams = { ...mockSurveyParams };
		render( <PaymentGatewaysConfirmation /> );

		// Trigger the disable flow.
		triggerStripeDisable();

		// Confirmation modal should appear.
		expect( screen.getByText( 'Disable Stripe' ) ).toBeInTheDocument();

		// Click "Disable" to confirm.
		await userEvent.click( screen.getByText( 'Disable' ) );

		// Exit survey modal should now appear.
		expect( screen.getByTitle( 'Exit Survey' ) ).toBeInTheDocument();
	} );

	it( 'skips exit survey when cooldown is active', async () => {
		const recent = new Date();
		recent.setDate( recent.getDate() - 1 );
		global.wcStripeExitSurveyParams = {
			...mockSurveyParams,
			exit_survey_last_shown: recent.toISOString(),
		};
		render( <PaymentGatewaysConfirmation /> );

		triggerStripeDisable();

		// Confirmation modal should appear.
		expect( screen.getByText( 'Disable Stripe' ) ).toBeInTheDocument();

		// Click "Disable" to confirm.
		await userEvent.click( screen.getByText( 'Disable' ) );

		// Exit survey should NOT appear — cooldown is active.
		expect( screen.queryByTitle( 'Exit Survey' ) ).not.toBeInTheDocument();
	} );

	it( 'skips exit survey when params are undefined', async () => {
		// wcStripeExitSurveyParams is intentionally not set.
		render( <PaymentGatewaysConfirmation /> );

		triggerStripeDisable();

		expect( screen.getByText( 'Disable Stripe' ) ).toBeInTheDocument();

		await userEvent.click( screen.getByText( 'Disable' ) );

		// Exit survey should NOT appear.
		expect( screen.queryByTitle( 'Exit Survey' ) ).not.toBeInTheDocument();
	} );

	it( 'proceeds with disable after exit survey is closed', async () => {
		global.wcStripeExitSurveyParams = { ...mockSurveyParams };
		render( <PaymentGatewaysConfirmation /> );

		triggerStripeDisable();
		await userEvent.click( screen.getByText( 'Disable' ) );

		// Exit survey is showing.
		expect( screen.getByTitle( 'Exit Survey' ) ).toBeInTheDocument();

		// Close the survey — the handler is async (apiFetch), so we need to wait.
		await userEvent.click( screen.getByLabelText( 'Close' ) );

		await waitFor( () => {
			expect(
				screen.queryByTitle( 'Exit Survey' )
			).not.toBeInTheDocument();
		} );
	} );
} );
