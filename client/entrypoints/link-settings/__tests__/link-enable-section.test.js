import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import LinkEnableSection from '../link-enable-section';
import { useEnabledPaymentMethodIds } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
} ) );

describe( 'LinkEnableSection', () => {
	let updateEnabledMethodIdsMock;

	beforeEach( () => {
		updateEnabledMethodIdsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledMethodIdsMock,
		] );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders the checkbox control with correct label and help text', () => {
		render( <LinkEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Link by Stripe/i,
		} );
		expect( checkbox ).toBeInTheDocument();
		expect( checkbox ).not.toBeChecked();

		expect(
			screen.getByText(
				'When enabled, customers will be able to pay with Link by Stripe for a fast, simple, and secure checkout experience.'
			)
		).toBeInTheDocument();
	} );

	it( 'renders checkbox as checked when Link is enabled', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'link' ],
			updateEnabledMethodIdsMock,
		] );

		render( <LinkEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Link by Stripe/i,
		} );
		expect( checkbox ).toBeChecked();
	} );

	it( 'renders checkbox as unchecked when Link is disabled', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledMethodIdsMock,
		] );

		render( <LinkEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Link by Stripe/i,
		} );
		expect( checkbox ).not.toBeChecked();
	} );

	it( 'adds Link to enabled methods when checkbox is checked', async () => {
		render( <LinkEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Link by Stripe/i,
		} );

		await userEvent.click( checkbox );

		expect( updateEnabledMethodIdsMock ).toHaveBeenCalledWith( [
			'card',
			'link',
		] );
	} );

	it( 'removes Link from enabled methods when checkbox is unchecked', async () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'link' ],
			updateEnabledMethodIdsMock,
		] );

		render( <LinkEnableSection /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: /Enable Link by Stripe/i,
		} );

		await userEvent.click( checkbox );

		expect( updateEnabledMethodIdsMock ).toHaveBeenCalledWith( [ 'card' ] );
	} );

	it( 'renders the component with express-checkout-settings class', () => {
		const { container } = render( <LinkEnableSection /> );

		const card = container.querySelector( '.express-checkout-settings' );
		expect( card ).toBeInTheDocument();
	} );
} );
