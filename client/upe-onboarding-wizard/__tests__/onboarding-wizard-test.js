import React from 'react';
import { screen, render } from '@testing-library/react';
import OnboardingWizard from '../onboarding-wizard';

jest.mock(
	'wcstripe/components/payment-method-capability-status-pill',
	() => () => null
);
jest.mock( 'wcstripe/data', () => ( {
	useSettings: jest.fn().mockReturnValue( {} ),
	useManualCapture: jest.fn().mockReturnValue( [] ),
	useEnabledPaymentMethodIds: jest.fn().mockReturnValue( [ [] ] ),
	useGetAvailablePaymentMethodIds: jest.fn().mockReturnValue( [] ),
} ) );

// remove once manual capture hook is implemented
jest.mock(
	'wcstripe/settings/payments-and-transactions-section/data-mock',
	() => ( {
		useManualCapture: jest.fn().mockReturnValue( [] ),
	} )
);

describe( 'OnboardingWizard', () => {
	it( 'should render the onboarding wizard', () => {
		render( <OnboardingWizard /> );

		expect(
			screen.getByText( 'Enable the new Stripe checkout experience' )
		).toBeInTheDocument();
	} );
} );
