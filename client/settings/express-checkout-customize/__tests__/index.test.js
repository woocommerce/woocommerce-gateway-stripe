import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
	ExpressCheckoutAppearanceOverrideNotice,
	ExpressCheckoutButtonSizeControl,
	ExpressCheckoutLocationsControl,
	getExpressCheckoutLocationKeys,
} from '..';

// `<Notice />` calls into `@wordpress/a11y`'s speak(); silence it in tests.
const realPathToA11yModule =
	'@wordpress/components/node_modules/@wordpress/a11y';
jest.mock( realPathToA11yModule, () => ( {
	...jest.requireActual( realPathToA11yModule ),
	speak: jest.fn(),
} ) );

describe( 'getExpressCheckoutLocationKeys', () => {
	it( 'omits change_payment_method by default', () => {
		expect( getExpressCheckoutLocationKeys() ).toEqual( [
			'checkout',
			'product',
			'cart',
		] );
	} );

	it( 'includes change_payment_method when requested', () => {
		expect(
			getExpressCheckoutLocationKeys( {
				includeChangePaymentMethod: true,
			} )
		).toEqual( [ 'checkout', 'product', 'cart', 'change_payment_method' ] );
	} );
} );

describe( 'ExpressCheckoutLocationsControl', () => {
	it( 'disables every checkbox when the method is disabled', () => {
		render(
			<ExpressCheckoutLocationsControl
				methodEnabled={ false }
				locations={ [ 'checkout' ] }
				onChange={ jest.fn() }
			/>
		);

		screen
			.getAllByRole( 'checkbox' )
			.forEach( ( checkbox ) => expect( checkbox ).toBeDisabled() );
	} );

	it( 'hides the subscriptions location unless requested', () => {
		const { rerender } = render(
			<ExpressCheckoutLocationsControl
				methodEnabled
				locations={ [] }
				onChange={ jest.fn() }
			/>
		);
		expect(
			screen.queryByRole( 'checkbox', {
				name: /change payment method/i,
			} )
		).not.toBeInTheDocument();

		rerender(
			<ExpressCheckoutLocationsControl
				methodEnabled
				locations={ [] }
				onChange={ jest.fn() }
				showChangePaymentMethod
			/>
		);
		expect(
			screen.getByRole( 'checkbox', {
				name: /change payment method/i,
			} )
		).toBeInTheDocument();
	} );

	it( 'adds a location when checked and removes it when unchecked', async () => {
		const onChange = jest.fn();
		const { rerender } = render(
			<ExpressCheckoutLocationsControl
				methodEnabled
				locations={ [ 'checkout' ] }
				onChange={ onChange }
			/>
		);

		await userEvent.click(
			screen.getByRole( 'checkbox', { name: 'Cart' } )
		);
		expect( onChange ).toHaveBeenLastCalledWith( [ 'checkout', 'cart' ] );

		rerender(
			<ExpressCheckoutLocationsControl
				methodEnabled
				locations={ [ 'checkout', 'cart' ] }
				onChange={ onChange }
			/>
		);
		await userEvent.click(
			screen.getByRole( 'checkbox', { name: 'Checkout' } )
		);
		expect( onChange ).toHaveBeenLastCalledWith( [ 'cart' ] );
	} );
} );

describe( 'ExpressCheckoutAppearanceOverrideNotice', () => {
	it( 'renders nothing when not overridden', () => {
		const { container } = render(
			<ExpressCheckoutAppearanceOverrideNotice isOverridden={ false } />
		);
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders the warning when overridden', () => {
		render( <ExpressCheckoutAppearanceOverrideNotice isOverridden /> );
		expect( screen.getByText( /may be overridden/ ) ).toBeInTheDocument();
	} );
} );

describe( 'ExpressCheckoutButtonSizeControl', () => {
	it( 'renders the three size options and reports changes', async () => {
		const onChange = jest.fn();
		render(
			<ExpressCheckoutButtonSizeControl
				size="default"
				onChange={ onChange }
			/>
		);

		expect( screen.getByText( /Small/ ) ).toBeInTheDocument();
		expect( screen.getByText( /Large/ ) ).toBeInTheDocument();

		await userEvent.click( screen.getByLabelText( /Large/ ) );
		expect( onChange ).toHaveBeenCalledWith( 'large' );
	} );
} );
