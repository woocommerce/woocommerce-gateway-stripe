import React from 'react';
import { screen, render } from '@testing-library/react';

import OnboardingWizard from '../onboarding-wizard';

describe( 'OnboardingWizard', () => {
	it( 'should render the onboarding wizard', () => {
		render( <OnboardingWizard /> );

		expect ( screen.getByText( 'Hello wizard page' ) ).toBeInTheDocument();
	} );

} );
