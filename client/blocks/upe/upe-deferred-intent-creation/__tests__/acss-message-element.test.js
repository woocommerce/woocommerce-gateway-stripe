import { render, screen } from '@testing-library/react';
import AcssMessageElement from '../acss-message-element';
import { PAYMENT_METHOD_ACSS } from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/blocks/utils', () => ( {
	getStripeImageUrl: ( name ) => `/assets/images/${ name }.svg`,
} ) );

const NOTICE_TEXT =
	'After submission, you will need to authorize the payment with your bank.';

describe( 'AcssMessageElement', () => {
	it( 'renders the authorization notice text', () => {
		render( <AcssMessageElement /> );

		expect( screen.getByText( NOTICE_TEXT ) ).toBeInTheDocument();
	} );

	it( 'renders an SVG icon that references the redirect asset via <use>', () => {
		const { container } = render( <AcssMessageElement /> );

		const icon = container.querySelector(
			'svg.wc-stripe-acss-notice__icon'
		);
		expect( icon ).toBeInTheDocument();
		expect( icon.getAttribute( 'viewBox' ) ).toBe( '0 0 48 40' );

		const use = icon.querySelector( 'use' );
		expect( use ).toBeInTheDocument();
		expect( use.getAttribute( 'href' ) ).toBe(
			'/assets/images/acss-redirect.svg#icon'
		);
	} );
} );

describe( 'AcssMessageElement gating in payment-processor', () => {
	// Mirrors the conditional at
	// client/blocks/upe/upe-deferred-intent-creation/payment-processor.js
	const AcssGate = ( { paymentMethodId } ) =>
		paymentMethodId === PAYMENT_METHOD_ACSS ? <AcssMessageElement /> : null;

	it( 'renders the notice when paymentMethodId is ACSS', () => {
		render( <AcssGate paymentMethodId={ PAYMENT_METHOD_ACSS } /> );

		expect( screen.getByText( NOTICE_TEXT ) ).toBeInTheDocument();
	} );

	it( 'does not render the notice for non-ACSS payment methods', () => {
		render( <AcssGate paymentMethodId="card" /> );

		expect( screen.queryByText( NOTICE_TEXT ) ).not.toBeInTheDocument();
	} );
} );
