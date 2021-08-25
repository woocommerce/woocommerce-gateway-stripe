/**
 * External dependencies
 */
 import React from 'react';
 import { Card, CardBody } from '@wordpress/components';

 /**
  * Internal dependencies
  */
 import Wizard from './wizard/wrapper';
 import WizardTask from './wizard/task';
 import WizardTaskList from './wizard/task-list';
 import EnableUpePreviewTask from './upe-preview-methods-selector/enable-upe-preview-task';
 import SetupCompleteTask from './upe-preview-methods-selector/setup-complete-task';
 import AddPaymentMethodsTask from './upe-preview-methods-selector/add-payment-methods-task';
 import './index.scss';

 const OnboardingWizard = () => {
	 const isUpeEnabled = true; //TODO: feature flag.

	return (
		<Card className="upe-preview-methods-selector">
			<CardBody>
				<Wizard
					defaultActiveTask={
						isUpeEnabled
							? 'add-payment-methods'
							: 'enable-upe-preview'
					}
					defaultCompletedTasks={ {
						'enable-upe-preview': isUpeEnabled,
					} }
				>
					<WizardTaskList>
						<WizardTask id="enable-upe-preview">
							<EnableUpePreviewTask />
						</WizardTask>
						<WizardTask id="add-payment-methods">
							{/* <AddPaymentMethodsTask /> */}
							box2
						</WizardTask>
						<WizardTask id="setup-complete">
							{/* <SetupCompleteTask /> */}
							box3
						</WizardTask>
					</WizardTaskList>
				</Wizard>
			</CardBody>
		</Card>
	);
};

export default OnboardingWizard;
