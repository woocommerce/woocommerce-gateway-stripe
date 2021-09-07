/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentRequestsSettings from '../payment-request-settings';
import PaymentRequestButtonPreview from '../payment-request-button-preview';

/* @todo: Add back when we have data persistence.
import {
	usePaymentRequestButtonType,
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
} from '../../../data';

jest.mock( '../../../data', () => ( {
	usePaymentRequestButtonType: jest.fn().mockReturnValue( [ 'buy' ] ),
	usePaymentRequestButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
	usePaymentRequestButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
} ) );
*/

const usePaymentRequestButtonType = jest.fn().mockReturnValue( [ 'buy' ] );
const usePaymentRequestButtonSize = jest.fn().mockReturnValue( [ 'default' ] );
const usePaymentRequestButtonTheme = jest.fn().mockReturnValue( [ 'dark' ] );

jest.mock( '../payment-request-button-preview' );
PaymentRequestButtonPreview.mockImplementation( () => '<></>' );

jest.mock( '../utils/utils', () => ( {
	getPaymentRequestData: jest.fn().mockReturnValue( {
		publishableKey: 'pk_test_123',
		accountId: '0001',
		locale: 'en',
	} ),
} ) );

describe( 'PaymentRequestsSettings', () => {
	it( 'renders settings with defaults', () => {
		render( <PaymentRequestsSettings /> );

		const heading = screen.queryByRole( 'heading', {
			name: 'Google Pay / Apple Pay',
		} );
		expect( heading ).toBeInTheDocument();

		// confirm settings headings
		expect(
			screen.queryByRole( 'heading', { name: 'Call to action' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'heading', { name: 'Appearance' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed
		const [ ctaRadio, sizeRadio, themeRadio ] = screen.queryAllByRole(
			'radio'
		);

		expect( ctaRadio ).toBeInTheDocument();
		expect( sizeRadio ).toBeInTheDocument();
		expect( themeRadio ).toBeInTheDocument();

		// confirm default values
		expect( screen.getByLabelText( 'Buy' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Default (40 px)' ) ).toBeChecked();
		expect( screen.getByLabelText( /Dark/ ) ).toBeChecked();
	} );

	// This has to be skipped because the hooks in PaymentRequestsCustomizer do not exist yet.
	it.skip( 'triggers the hooks when the settings are being interacted with', () => {
		const setButtonTypeMock = jest.fn();
		const setButtonSizeMock = jest.fn();
		const setButtonThemeMock = jest.fn();
		usePaymentRequestButtonType.mockReturnValue( [
			'buy',
			setButtonTypeMock,
		] );
		usePaymentRequestButtonSize.mockReturnValue( [
			'default',
			setButtonSizeMock,
		] );
		usePaymentRequestButtonTheme.mockReturnValue( [
			'dark',
			setButtonThemeMock,
		] );

		render( <PaymentRequestsSettings /> );

		expect( setButtonTypeMock ).not.toHaveBeenCalled();
		expect( setButtonSizeMock ).not.toHaveBeenCalled();
		expect( setButtonThemeMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( /Light/ ) );
		expect( setButtonThemeMock ).toHaveBeenCalledWith( 'light' );

		userEvent.click( screen.getByLabelText( 'Book' ) );
		expect( setButtonTypeMock ).toHaveBeenCalledWith( 'book' );

		userEvent.click( screen.getByLabelText( 'Large (56 px)' ) );
		expect( setButtonSizeMock ).toHaveBeenCalledWith( 'large' );
	} );
} );
