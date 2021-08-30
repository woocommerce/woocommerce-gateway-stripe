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
import './style.scss';

const OnboardingWizard = () => {
	return (
		<Card className="upe-preview-methods-selector">
			<CardBody>
				<Wizard defaultActiveTask="enable-upe-preview">
					<WizardTaskList>
						<WizardTask id="enable-upe-preview">
							<EnableUpePreviewTask />
						</WizardTask>
						<WizardTask id="add-payment-methods">
							<AddPaymentMethodsTask />
						</WizardTask>
						<WizardTask id="setup-complete">
							<SetupCompleteTask />
						</WizardTask>
					</WizardTaskList>
				</Wizard>
			</CardBody>
		</Card>
	);
};

export default OnboardingWizard;
