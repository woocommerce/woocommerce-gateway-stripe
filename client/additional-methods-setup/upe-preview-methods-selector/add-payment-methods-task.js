/**
 * External dependencies
 */
import React, {
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useState,
} from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, ExternalLink } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useSettings,
} from '../../data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';


const AddPaymentMethodsTask = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const { setCompleted } = useContext( WizardTaskContext );
	const isSaving = false;
	const [paymentMethodsState, setPaymentMethodsState] = useState({
		'giropay': true,
		'sofort': true,
		'sepa_debit': true
	});

	const handleContinueClick = useCallback( () => {
		setCompleted( true, 'setup-complete' );
	}, [ setCompleted ] );

	const paymentsCheckboxHandler = (name, enabled) => {
		setPaymentMethodsState({...paymentMethodsState, ...{
			[name]: enabled
		}});
	}


	return (
		<WizardTaskItem
			className="add-payment-methods-task"
			title={ __(
				'Boost your sales with payment methods',
				'woocommerce-gateway-stripe'
			) }
			index={ 2 }
		>
			<CollapsibleBody>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ interpolateComponents( {
						mixedString: __(
							'For best results, we recommend adding all available payment methods. ' +
								"We'll only show your customer the most relevant payment methods " +
								'based on their location. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
							'woocommerce-gateway-stripe'
						),
						components: {
							learnMoreLink: (
								// eslint-disable-next-line max-len
								<ExternalLink href="https://docs.woocommerce.com/document/payments/additional-payment-methods/#available-methods" />
							),
						},
					} ) }
				</p>
				<Card className="add-payment-methods-task__payment-selector-wrapper">
					<CardBody>
						{ /* eslint-disable-next-line max-len */ }
						<p className="add-payment-methods-task__payment-selector-title wcpay-wizard-task__description-element">
							{ __(
								'Payments accepted at checkout',
								'woocommerce-gateway-stripe'
							) }
						</p>

						<PaymentMethodCheckboxes>
							<PaymentMethodCheckbox
								name='giropay'
								onChange={paymentsCheckboxHandler}
								checked={paymentMethodsState['giropay']}
							>

							</PaymentMethodCheckbox>

						</PaymentMethodCheckboxes>

					</CardBody>
				</Card>
				<Button
					isBusy={ isSaving }
					disabled={ isSaving }
					onClick={ handleContinueClick }
					isPrimary
				>
					{ __( 'Add payment methods', 'woocommerce-gateway-stripe' ) }
				</Button>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddPaymentMethodsTask;
