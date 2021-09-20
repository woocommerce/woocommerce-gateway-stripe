import { render, screen } from '@testing-library/react';
import WizardTaskContext from '../../wizard/task/context';
import SetupComplete from '../setup-complete-task';
import WizardContext from '../../wizard/wrapper/context';
import { useEnabledPaymentMethodIds } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
} ) );

describe( 'SetupComplete', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay', 'sofort' ],
			() => null,
		] );
	} );

	it( 'renders setup complete messaging when context value says that methods have not changed', () => {
		render(
			<WizardContext.Provider
				value={ {
					completedTasks: {
						'add-payment-methods': {
							initialMethods: [ 'card', 'giropay', 'sofort' ],
						},
					},
				} }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect( screen.getByText( /Setup complete/ ) ).toHaveTextContent(
			'Setup complete!'
		);
	} );

	it( 'renders setup complete messaging when context value says that one payment method has been removed', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sofort' ],
			() => null,
		] );
		render(
			<WizardContext.Provider
				value={ {
					completedTasks: {
						'add-payment-methods': {
							initialMethods: [ 'card', 'giropay', 'sofort' ],
						},
					},
				} }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect( screen.getByText( /Setup complete/ ) ).toHaveTextContent(
			'Setup complete!'
		);
	} );

	it( 'renders setup complete messaging when context value says that one payment method has been added', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay' ],
			() => null,
		] );
		render(
			<WizardContext.Provider
				value={ {
					completedTasks: {
						'add-payment-methods': {
							initialMethods: [ 'card' ],
						},
					},
				} }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect( screen.getByText( /Setup complete/ ) ).toHaveTextContent(
			'Setup complete! One new payment method is now live on your store!'
		);
	} );

	it( 'renders setup complete messaging when context value says that more than one payment method has been added', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay', 'sofort', 'sepa_debit' ],
			() => null,
		] );
		render(
			<WizardContext.Provider
				value={ {
					completedTasks: {
						'add-payment-methods': {
							initialMethods: [ 'card' ],
						},
					},
				} }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect( screen.getByText( /Setup complete/ ) ).toHaveTextContent(
			'Setup complete! 3 new payment methods are now live on your store!'
		);
	} );
} );
