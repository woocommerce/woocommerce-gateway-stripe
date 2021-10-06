import { __ } from '@wordpress/i18n';
import React, {
	useCallback,
	useContext,
	useState,
	useEffect,
	useMemo,
} from 'react';
import styled from '@emotion/styled';
import { Button, Card, CardBody, ExternalLink } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import {
	useGetAvailablePaymentMethodIds,
	useEnabledPaymentMethodIds,
	useSettings,
} from '../../data';
import PaymentMethodCheckbox from './payment-method-checkbox';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';

const HeadingWrapper = styled.div`
	display: flex;
	margin-bottom: 1em;
	gap: 8px;

	> * {
		margin: 0;
	}
`;

const usePaymentMethodsCheckboxState = () => {
	const [ initialEnabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const [ paymentMethodsState, setPaymentMethodsState ] = useState( {} );

	useEffect( () => {
		setPaymentMethodsState(
			initialEnabledPaymentMethodIds.reduce(
				( map, paymentMethod ) => ( {
					...map,
					[ paymentMethod ]: true,
				} ),
				{}
			)
		);
	}, [ initialEnabledPaymentMethodIds, setPaymentMethodsState ] );

	const handleChange = useCallback(
		( paymentMethodName, enabled ) => {
			setPaymentMethodsState( ( oldValues ) => ( {
				...oldValues,
				[ paymentMethodName ]: enabled,
			} ) );
		},
		[ setPaymentMethodsState ]
	);

	return [ paymentMethodsState, handleChange ];
};

const ContinueButton = ( { paymentMethodsState } ) => {
	const { setCompleted } = useContext( WizardTaskContext );
	const [
		initialEnabledPaymentMethodIds,
		updateEnabledPaymentMethodIds,
	] = useEnabledPaymentMethodIds();

	const { saveSettings, isSaving } = useSettings();

	const checkedPaymentMethods = useMemo(
		() =>
			Object.entries( paymentMethodsState )
				.map( ( [ method, enabled ] ) => enabled && method )
				.filter( Boolean ),
		[ paymentMethodsState ]
	);

	const unCheckedPaymentMethods = useMemo(
		() =>
			Object.entries( paymentMethodsState )
				.map( ( [ method, enabled ] ) => ! enabled && method )
				.filter( Boolean ),
		[ paymentMethodsState ]
	);

	const handleContinueClick = useCallback( () => {
		// creating a separate callback, so that the main thread isn't blocked on click of the button
		const callback = async () => {
			updateEnabledPaymentMethodIds( [
				// adding the newly selected payment methods and removing them from the `initialEnabledPaymentMethodIds` if unchecked
				...new Set(
					[
						...initialEnabledPaymentMethodIds,
						...checkedPaymentMethods,
					].filter(
						( method ) =>
							! unCheckedPaymentMethods.includes( method )
					)
				),
			] );

			const isSuccess = await saveSettings();
			if ( ! isSuccess ) {
				// restoring the state, in case of soft route
				updateEnabledPaymentMethodIds( initialEnabledPaymentMethodIds );
				return;
			}

			setCompleted(
				{
					initialMethods: initialEnabledPaymentMethodIds,
				},
				'setup-complete'
			);
		};

		callback();
	}, [
		unCheckedPaymentMethods,
		checkedPaymentMethods,
		updateEnabledPaymentMethodIds,
		saveSettings,
		setCompleted,
		initialEnabledPaymentMethodIds,
	] );

	return (
		<Button
			isBusy={ isSaving }
			disabled={ isSaving || checkedPaymentMethods.length < 1 }
			onClick={ handleContinueClick }
			isPrimary
		>
			{ __( 'Add payment methods', 'woocommerce-gateway-stripe' ) }
		</Button>
	);
};

const AddPaymentMethodsTask = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();

	// I am using internal state in this component
	// and committing the changes on `initialEnabledPaymentMethodIds` only when the "continue" button is clicked.
	// Otherwise a user could navigate to another page via soft-routing and the settings would be in un-saved state,
	// possibly causing errors.
	const [
		paymentMethodsState,
		handlePaymentMethodChange,
	] = usePaymentMethodsCheckboxState();

	const handleSelectAllClick = () => {
		availablePaymentMethods.forEach( ( method ) => {
			handlePaymentMethodChange( method, true );
		} );
	};

	return (
		<WizardTaskItem
			className="add-payment-methods-task"
			title={ __(
				'Review accepted payment methods',
				'woocommerce-gateway-stripe'
			) }
			index={ 2 }
		>
			<CollapsibleBody>
				<p className="wcstripe-wizard-task__description-element is-muted-color">
					{ interpolateComponents( {
						mixedString: __(
							"We've added methods that you'd already enabled. For best results, we recommand adding " +
								"all available payment methods. We'll only show your customers the most relevant payment " +
								'methods based on their location and purchasing history. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
							'woocommerce-gateway-stripe'
						),
						components: {
							learnMoreLink: <ExternalLink href="TODO?" />,
						},
					} ) }
				</p>
				<Card className="add-payment-methods-task__payment-selector-wrapper">
					<CardBody>
						<LoadableSettingsSection numLines={ 20 }>
							<HeadingWrapper>
								{ /* eslint-disable-next-line max-len */ }
								<p className="add-payment-methods-task__payment-selector-title wcstripe-wizard-task__description-element is-headline">
									{ __(
										'Payments accepted at checkout',
										'woocommerce-gateway-stripe'
									) }
								</p>
								<Button
									isLink
									onClick={ handleSelectAllClick }
									className="add-payment-methods-task__select-all-button"
								>
									{ __(
										'Select all',
										'woocommerce-gateway-stripe'
									) }
								</Button>
							</HeadingWrapper>

							<ul>
								{ availablePaymentMethods.map(
									( paymentMethodId ) => (
										<PaymentMethodCheckbox
											key={ paymentMethodId }
											id={ paymentMethodId }
											onChange={
												handlePaymentMethodChange
											}
											checked={
												paymentMethodsState[
													paymentMethodId
												]
											}
										/>
									)
								) }
							</ul>
						</LoadableSettingsSection>
					</CardBody>
				</Card>
				<LoadableSettingsSection numLines={ 3 }>
					<ContinueButton
						paymentMethodsState={ paymentMethodsState }
					/>
				</LoadableSettingsSection>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddPaymentMethodsTask;
