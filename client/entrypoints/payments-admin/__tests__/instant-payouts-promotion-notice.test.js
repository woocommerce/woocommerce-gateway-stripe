import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import InstantPayoutsPromotionNotice from '../instant-payouts-promotion-notice';

// Mock @wordpress/ui components as Jest currently can't process some *.mjs files
// from the @wordpress/theme package.
const passthroughDiv = ( { children } ) => <div>{ children }</div>;

jest.mock( '@wordpress/ui', () => ( {
	Notice: {
		Root: passthroughDiv,
		Title: passthroughDiv,
		Description: passthroughDiv,
		Actions: passthroughDiv,
		ActionLink: ( { href, children } ) => <a href={ href }>{ children }</a>,
		CloseIcon: ( { label, onClick } ) => (
			<button onClick={ onClick }>{ label }</button>
		),
	},
} ) );

describe( 'InstantPayoutsPromotionNotice', () => {
	it( 'renders the message and links to the payouts URL', () => {
		render(
			<InstantPayoutsPromotionNotice
				message="You currently have $100.00 available."
				payoutsUrl="https://dashboard.stripe.com/test/payouts/"
				onDismiss={ () => {} }
			/>
		);

		expect(
			screen.getByText( 'Improve your cash flow' )
		).toBeInTheDocument();
		expect(
			screen.getByText( /You currently have \$100\.00 available\./ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Pay out instantly' } )
		).toHaveAttribute(
			'href',
			'https://dashboard.stripe.com/test/payouts/'
		);
	} );

	it( 'calls onDismiss when the close button is clicked', () => {
		const onDismiss = jest.fn();

		render(
			<InstantPayoutsPromotionNotice
				message="You currently have $100.00 available."
				payoutsUrl="https://dashboard.stripe.com/test/payouts/"
				onDismiss={ onDismiss }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Dismiss' } ) );

		expect( onDismiss ).toHaveBeenCalledTimes( 1 );
	} );
} );
