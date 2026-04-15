import { render, screen } from '@testing-library/react';
import RedirectMessageElement from '../redirect-message-element';
import { PAYMENT_METHOD_ACSS } from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/blocks/utils', () => ( {
	getStripeImageUrl: ( name ) => `/assets/images/${ name }.svg`,
} ) );

const NOTICE_TEXT =
	'After submission, you will need to authorize the payment with your bank.';

describe( 'RedirectMessageElement', () => {
	it( 'renders the text passed via the text prop', () => {
		render( <RedirectMessageElement text={ NOTICE_TEXT } /> );

		expect( screen.getByText( NOTICE_TEXT ) ).toBeInTheDocument();
	} );

	it( 'renders an SVG icon that references the redirect asset via <use>', () => {
		const { container } = render(
			<RedirectMessageElement text={ NOTICE_TEXT } />
		);

		const icon = container.querySelector(
			'svg.wc-stripe-redirect-notice__icon'
		);
		expect( icon ).toBeInTheDocument();
		expect( icon.getAttribute( 'viewBox' ) ).toBe( '0 0 48 40' );

		const use = icon.querySelector( 'use' );
		expect( use ).toBeInTheDocument();
		expect( use.getAttribute( 'href' ) ).toBe(
			'/assets/images/payment-redirect.svg#icon'
		);
	} );
} );

describe( 'RedirectMessageElement gating in payment-processor', () => {
	// Mirrors the conditional at
	// client/blocks/upe/upe-deferred-intent-creation/payment-processor.js
	const AcssGate = ( { paymentMethodId } ) =>
		paymentMethodId === PAYMENT_METHOD_ACSS ? (
			<RedirectMessageElement text={ NOTICE_TEXT } />
		) : null;

	it( 'renders the notice when paymentMethodId is ACSS', () => {
		render( <AcssGate paymentMethodId={ PAYMENT_METHOD_ACSS } /> );

		expect( screen.getByText( NOTICE_TEXT ) ).toBeInTheDocument();
	} );

	it( 'does not render the notice for non-ACSS payment methods', () => {
		render( <AcssGate paymentMethodId="card" /> );

		expect( screen.queryByText( NOTICE_TEXT ) ).not.toBeInTheDocument();
	} );
} );
