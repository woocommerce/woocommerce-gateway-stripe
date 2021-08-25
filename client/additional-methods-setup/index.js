/**
 * External dependencies
 */
 import React from 'react';
 import ReactDOM from 'react-dom';

 /**
  * Internal dependencies
  */
 import OnboardingWizard from './onboarding-wizard';

 const container = document.getElementById(
     'wc-stripe-onboarding-wizard-container'
 );

 if ( container ) {
     ReactDOM.render( <OnboardingWizard />, container );
 }
