import { __ } from '@wordpress/i18n';
import React, { useCallback, useContext, useState } from 'react';
import {
	Button,
	Card,
	CardBody,
	CheckboxControl,
	ExternalLink,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { Icon, store, people } from '@wordpress/icons';
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import Pill from '../../components/pill';
import { useManualCapture, useSettings } from 'wcstripe/data';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import './style.scss';

const EnableUpePreviewTask = () => {
	const { setCompleted } = useContext( WizardTaskContext );
	const { setIsUpeEnabled, status } = useContext( UpeToggleContext );
	const { saveSettings, isSaving: isSavingSettings } = useSettings();
	const [
		initialIsManualCaptureEnabled,
		setIsManualCaptureEnabled,
	] = useManualCapture();

	const [
		internalIsAutomaticCaptureEnabled,
		setInternalIsAutomaticCaptureEnabled,
	] = useState( ! initialIsManualCaptureEnabled );

	const handleContinueClick = useCallback( () => {
		setIsManualCaptureEnabled( false );
		Promise.all( [ saveSettings(), setIsUpeEnabled( true ) ] ).then(
			( [ saveSettingsResult ] ) => {
				// when an error occurs, `saveSettings()` returns `false` (and still resolves)
				if ( saveSettingsResult === false ) {
					return;
				}

				setCompleted( true, 'add-payment-methods' );
			}
		);
	}, [
		setCompleted,
		setIsUpeEnabled,
		setIsManualCaptureEnabled,
		saveSettings,
	] );

	return (
		<WizardTaskItem
			title={ interpolateComponents( {
				mixedString: __(
					'{{wrapper}}Enable the new Stripe checkout experience{{/wrapper}} ' +
						'{{earlyAccessWrapper}}Early access{{/earlyAccessWrapper}}',
					'woocommerce-gateway-stripe'
				),
				components: {
					wrapper: <span />,
					earlyAccessWrapper: <Pill />,
				},
			} ) }
			index={ 1 }
		>
			<CollapsibleBody className="enable-upe-preview__body">
				<p className="wcstripe-wizard-task__description-element is-muted-color">
					{ interpolateComponents( {
						mixedString: __(
							'Get early access to additional payment methods and an improved checkout experience, ' +
								'coming soon to Stripe. {{learnMoreLink /}}',
							'woocommerce-gateway-stripe'
						),
						components: {
							learnMoreLink: (
								// eslint-disable-next-line max-len
								<ExternalLink href="https://woocommerce.com/document/stripe/#new-checkout-experience">
									{ __(
										'Learn more',
										'woocommerce-gateway-stripe'
									) }
								</ExternalLink>
							),
						},
					} ) }
				</p>
				<div className="enable-upe-preview__advantages-wrapper">
					<Card className="enable-upe-preview__advantage">
						<div className="enable-upe-preview__advantage-color enable-upe-preview__advantage-color--for-you" />
						<CardBody>
							<Icon icon={ store } />
							<h3>For you</h3>
							<ul className="enable-upe-preview__advantage-features-list">
								<li>
									{ __(
										'Easily add new payment methods used by customers from all over the world.',
										'woocommerce-gateway-stripe'
									) }
								</li>
								<li>
									{ __(
										'FREE upgrade exclusive to Stripe users.',
										'woocommerce-gateway-stripe'
									) }
								</li>
								<li>
									{ __(
										'No hidden costs or setup fees.',
										'woocommerce-gateway-stripe'
									) }
								</li>
							</ul>
						</CardBody>
					</Card>
					<Card className="enable-upe-preview__advantage">
						<div className="enable-upe-preview__advantage-color enable-upe-preview__advantage-color--for-customers" />
						<CardBody>
							<Icon icon={ people } />
							<h3>For customers</h3>
							<ul className="enable-upe-preview__advantage-features-list">
								<li>
									{ __(
										'Checkout is completed without leaving your store.',
										'woocommerce-gateway-stripe'
									) }
								</li>
								<li>
									{ __(
										'Customers see only payment methods most relevant for them.',
										'woocommerce-gateway-stripe'
									) }
								</li>
								<li>
									{ __(
										'Refined checkout experience designed for desktop and mobile.',
										'woocommerce-gateway-stripe'
									) }
								</li>
							</ul>
						</CardBody>
					</Card>
				</div>
				{ initialIsManualCaptureEnabled && (
					<CheckboxControl
						label={ __(
							'Enable automatic capture of payments',
							'woocommerce-gateway-stripe'
						) }
						onChange={ setInternalIsAutomaticCaptureEnabled }
						checked={ internalIsAutomaticCaptureEnabled }
						help={ __(
							'In order to enable the new experience you need to enable the "automatic capture" of payments at checkout.',
							'woocommerce-gateway-stripe'
						) }
					/>
				) }
				<Button
					isBusy={ status === 'pending' || isSavingSettings }
					disabled={
						! internalIsAutomaticCaptureEnabled ||
						status === 'pending' ||
						isSavingSettings
					}
					onClick={ handleContinueClick }
					isPrimary
				>
					{ __( 'Enable', 'woocommerce-gateway-stripe' ) }
				</Button>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default EnableUpePreviewTask;
