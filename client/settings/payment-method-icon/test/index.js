/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

/**
 * Internal dependencies
 */
import PaymentMethodIcon from '..';

describe( 'PaymentMethodIcon', () => {
	test( 'renders Bancontact payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="bancontact" /> );
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders giropay payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="giropay" /> );
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders Sepa payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="sepa_debit" /> );
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders Sofort payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="sofort" /> );
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders p24 payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="p24" /> );
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders iDEAL payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="ideal" /> );
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders Bancontact payment method icon and label', () => {
		render( <PaymentMethodIcon name="bancontact" showName /> );

		const label = screen.queryByText( 'Bancontact' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders giropay payment method icon and label', () => {
		render( <PaymentMethodIcon name="giropay" showName /> );

		const label = screen.queryByText( 'giropay' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders Sepa payment method icon and label', () => {
		render( <PaymentMethodIcon name="sepa_debit" showName /> );

		const label = screen.queryByText( 'Direct debit payment' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders Sofort payment method icon and label', () => {
		render( <PaymentMethodIcon name="sofort" showName /> );

		const label = screen.queryByText( 'Sofort' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders p24 payment method icon and label', () => {
		render( <PaymentMethodIcon name="p24" showName /> );

		const label = screen.queryByText( 'Przelewy24 (P24)' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders iDEAL payment method icon and label', () => {
		render( <PaymentMethodIcon name="ideal" showName /> );

		const label = screen.queryByText( 'iDEAL' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders nothing when using an invalid icon name', () => {
		const { container } = render( <PaymentMethodIcon name="wrong" /> );

		expect( container.firstChild ).toBeNull();
	} );
} );
