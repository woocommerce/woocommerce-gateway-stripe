import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';
import PaymentMethodIcon from '..';

describe( 'PaymentMethodIcon', () => {
	test( 'renders Bancontact payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="bancontact" /> );
		expect( container.querySelector( 'img' ).src ).toContain(
			'test-file-stub'
		);
	} );

	test( 'renders Sepa payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="sepa_debit" /> );
		expect( container.querySelector( 'img' ).src ).toContain(
			'test-file-stub'
		);
	} );

	test( 'renders Sofort payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="sofort" /> );
		expect( container.querySelector( 'img' ).src ).toContain(
			'test-file-stub'
		);
	} );

	test( 'renders Bancontact payment method icon and label', () => {
		render( <PaymentMethodIcon name="bancontact" showName /> );

		const label = screen.queryByText( 'Bancontact' );
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

	test( 'renders nothing when using an invalid icon name', () => {
		const { container } = render( <PaymentMethodIcon name="wrong" /> );

		expect( container.firstChild ).toBeNull();
	} );
} );
