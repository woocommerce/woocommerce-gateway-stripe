import React, { useContext } from 'react';
import { Card, CardBody } from '@wordpress/components';
import Wizard from './wizard/wrapper/provider';
import WizardTask from './wizard/task';
import WizardTaskList from './wizard/task-list';
import EnableUpePreviewTask from './upe-preview-methods-selector/enable-upe-preview-task';
import SetupCompleteTask from './upe-preview-methods-selector/setup-complete-task';
import AddPaymentMethodsTask from './upe-preview-methods-selector/add-payment-methods-task';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import StripeBanner from 'wcstripe/components/stripe-banner';
import './style.scss';

const OnboardingWizard = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	return (
		<Card className="upe-preview-methods-selector">
			<StripeBanner />
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
