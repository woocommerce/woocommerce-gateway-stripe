import React from 'react';
import { render, screen } from '@testing-library/react';
import ExpressCheckoutSimulator from '..';
import { STATUS } from '../build-checks';

// `<Notice />` (via InlineNotice) calls into `@wordpress/a11y`'s speak(); silence it in tests.
const realPathToA11yModule =
	'@wordpress/components/node_modules/@wordpress/a11y';
jest.mock( realPathToA11yModule, () => ( {
	...jest.requireActual( realPathToA11yModule ),
	speak: jest.fn(),
} ) );

const passingChecks = [
	{
		key: 'account-connected',
		label: 'Stripe account connected',
		status: STATUS.PASS,
		detail: '',
		blockingText: 'Stripe account is not connected.',
	},
	{
		key: 'mode',
		label: 'Mode',
		status: STATUS.INFO,
		detail: 'Test',
	},
];

const locations = [
	{ key: 'product', label: 'Product page', enabled: true },
	{ key: 'cart', label: 'Cart', enabled: false },
	{ key: 'checkout', label: 'Checkout', enabled: true },
];

describe( 'ExpressCheckoutSimulator', () => {
	it( 'renders each eligibility check with its status modifier class', () => {
		const { container } = render(
			<ExpressCheckoutSimulator
				checks={ passingChecks }
				locations={ locations }
			/>
		);

		expect( container.querySelector( '.is-pass' ) ).toHaveTextContent(
			'Stripe account connected'
		);
		expect( container.querySelector( '.is-info' ) ).toHaveTextContent(
			'Mode'
		);
	} );

	it( 'shows a location when its toggle is enabled and no check blocks', () => {
		const { container } = render(
			<ExpressCheckoutSimulator
				checks={ passingChecks }
				locations={ locations }
			/>
		);

		const shown = container.querySelectorAll(
			'.express-checkout-simulator__location.is-shown'
		);
		const hidden = container.querySelectorAll(
			'.express-checkout-simulator__location.is-hidden'
		);

		// Product + Checkout are enabled; Cart is not.
		expect( shown ).toHaveLength( 2 );
		expect( hidden ).toHaveLength( 1 );
		expect( hidden[ 0 ] ).toHaveTextContent( 'Cart' );
		expect( hidden[ 0 ] ).toHaveTextContent(
			'Not enabled for this location in the settings above.'
		);
		expect( screen.getAllByText( 'Would display here.' ) ).toHaveLength(
			2
		);
	} );

	it( 'hides every location with the reason of a failing blocking check', () => {
		const checks = [
			{
				key: 'method-enabled',
				label: 'Apple Pay / Google Pay enabled',
				status: STATUS.FAIL,
				detail: '',
				blockingText: "Apple Pay / Google Pay isn't enabled.",
			},
			...passingChecks,
		];

		const { container } = render(
			<ExpressCheckoutSimulator
				checks={ checks }
				locations={ locations }
			/>
		);

		expect(
			container.querySelectorAll(
				'.express-checkout-simulator__location.is-shown'
			)
		).toHaveLength( 0 );
		// Even the enabled locations are hidden, all citing the blocker.
		expect(
			screen.getAllByText( "Apple Pay / Google Pay isn't enabled." )
		).toHaveLength( locations.length );
	} );

	it( 'uses the first failing blocking check as the reason (precedence)', () => {
		const checks = [
			{
				key: 'account-connected',
				label: 'Stripe account connected',
				status: STATUS.FAIL,
				detail: '',
				blockingText: 'Stripe account is not connected.',
			},
			{
				key: 'method-enabled',
				label: 'Link by Stripe enabled',
				status: STATUS.FAIL,
				detail: '',
				blockingText: "Link by Stripe isn't enabled.",
			},
		];

		render(
			<ExpressCheckoutSimulator
				checks={ checks }
				locations={ locations }
			/>
		);

		expect(
			screen.getAllByText( 'Stripe account is not connected.' )
		).toHaveLength( locations.length );
		expect(
			screen.queryByText( "Link by Stripe isn't enabled." )
		).not.toBeInTheDocument();
	} );

	it( 'ignores failing checks without blocking text', () => {
		const checks = [
			{
				key: 'https',
				label: 'HTTPS',
				// A fail with no blockingText (e.g. an info-only nuance) must not gate locations.
				status: STATUS.FAIL,
				detail: 'Required in live mode.',
			},
		];

		const { container } = render(
			<ExpressCheckoutSimulator
				checks={ checks }
				locations={ locations }
			/>
		);

		expect(
			container.querySelectorAll(
				'.express-checkout-simulator__location.is-shown'
			)
		).toHaveLength( 2 );
	} );
} );
