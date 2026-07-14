import { render, screen } from '@testing-library/react';
import { maybeShowCashAppLimitNotice } from 'wcstripe/stripe-utils/cash-app-limit-notice-handler';
import { callWhenElementIsAvailable } from 'wcstripe/blocks/upe/call-when-element-is-available';
import { CASH_APP_NOTICE_AMOUNT_THRESHOLD } from 'wcstripe/data/constants';

const wrapperElementClassName = 'woocommerce-checkout-payment';

jest.mock( 'wcstripe/blocks/upe/call-when-element-is-available' );

callWhenElementIsAvailable.mockImplementation(
	( selector, callable, params ) => {
		callable( ...params );
	}
);

describe( 'cash-app-limit-notice-handler', () => {
	it( 'does not render notice, cart amount is below threshold, wrapper not found and it is not a block checkout', () => {
		maybeShowCashAppLimitNotice(
			'.woocommerce-checkout-payment',
			0,
			false
		);

		expect(
			screen.queryByTestId( 'cash-app-limit-notice' )
		).not.toBeInTheDocument();
	} );

	it( 'does not render notice, cart amount is below threshold, but try to wait for wrapper to exist (block checkout)', () => {
		render( <div data-block-name="woocommerce/checkout" /> );

		maybeShowCashAppLimitNotice( '.woocommerce-checkout-payment', 0, true );

		expect(
			screen.queryByTestId( 'cash-app-limit-notice' )
		).not.toBeInTheDocument();
	} );

	it( 'render notice immediately (not block checkout, cart amount above threshold)', () => {
		render( <div className={ wrapperElementClassName } /> );

		maybeShowCashAppLimitNotice(
			'.woocommerce-checkout-payment',
			CASH_APP_NOTICE_AMOUNT_THRESHOLD + 1,
			false
		);

		expect(
			screen.queryByTestId( 'cash-app-limit-notice' )
		).toBeInTheDocument();
	} );

	it( 'render notice after wrapper exists on block checkout (cart amount above threshold)', async () => {
		function App() {
			const wrapper = document.createElement( 'div' );
			wrapper.classList.add( wrapperElementClassName );
			document.body.appendChild( wrapper );
			return <div data-block-name="woocommerce/checkout" />;
		}

		render( <App /> );

		maybeShowCashAppLimitNotice(
			'.' + wrapperElementClassName,
			CASH_APP_NOTICE_AMOUNT_THRESHOLD + 1,
			true
		);

		expect(
			screen.queryByTestId( 'cash-app-limit-notice' )
		).toBeInTheDocument();
	} );
} );
