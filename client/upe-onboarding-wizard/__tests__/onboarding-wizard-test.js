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

describe( 'OnboardingWizard', () => {
	it( 'should render the onboarding wizard', () => {
		render( <OnboardingWizard /> );

		expect(
			screen.getByText( 'Enable the new Stripe checkout experience' )
		).toBeInTheDocument();
	} );
} );
