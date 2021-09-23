import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DisableConfirmationModal from '../disable-confirmation-modal';

jest.mock( '../../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn().mockReturnValue( [ [] ] ),
	usePaymentRequestEnabledSettings: jest.fn().mockReturnValue( '' ),
} ) );

describe( 'DisableConfirmationModal', () => {
	it( 'calls the onClose handler on cancel', () => {
		const handleCloseMock = jest.fn();
		render( <DisableConfirmationModal onClose={ handleCloseMock } /> );

		expect( handleCloseMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Cancel' ) );

		expect( handleCloseMock ).toHaveBeenCalled();
	} );

	it( 'calls the onConfirm handler on cancel', () => {
		const handleConfirmMock = jest.fn();
		render( <DisableConfirmationModal onConfirm={ handleConfirmMock } /> );

		expect( handleConfirmMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Disable' ) );

		expect( handleConfirmMock ).toHaveBeenCalled();
	} );
} );
