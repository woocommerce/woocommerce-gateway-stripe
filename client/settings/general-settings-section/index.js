import { __ } from '@wordpress/i18n';
import React, { useContext, useState } from 'react';
import styled from '@emotion/styled';
import classNames from 'classnames';
import { Card, VisuallyHidden } from '@wordpress/components';
import LoadableSettingsSection from '../loadable-settings-section';
import AccountActivationNotice from '../account-activation-notice';
import LegacyExperienceTransitionNotice from '../notices/legacy-experience-transition';
import SectionHeading from './section-heading';
import SectionFooter from './section-footer';
import PaymentMethodsList from './payment-methods-list';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import { useAccount } from 'wcstripe/data/account';
import './styles.scss';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const AccountRefreshingOverlay = styled.div`
	position: relative;
	&.has-overlay {
		animation: loading-fade 1.6s ease-in-out infinite;

		&:after {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			content: ' ';
			background: white;
			opacity: 0.4;
		}
	}
`;

const GeneralSettingsSection = ( { onSaveChanges } ) => {
	const [ isChangingDisplayOrder, setIsChangingDisplayOrder ] = useState(
		false
	);
	const { isUpeEnabled, setIsUpeEnabled } = useContext( UpeToggleContext );
	const { isRefreshing } = useAccount();

	const onChangeDisplayOrder = ( isChanging, data = null ) => {
		setIsChangingDisplayOrder( isChanging );

		if ( data ) {
			onSaveChanges( 'ordered_payment_method_ids', data );
		}
	};

	return (
		<>
			<LegacyExperienceTransitionNotice
				isUpeEnabled={ isUpeEnabled }
				setIsUpeEnabled={ setIsUpeEnabled }
			/>
			<AccountActivationNotice />
			<StyledCard>
				<LoadableSettingsSection numLines={ 30 }>
					<SectionHeading
						isChangingDisplayOrder={ isChangingDisplayOrder }
						onChangeDisplayOrder={ onChangeDisplayOrder }
					/>
					{ isRefreshing && (
						<VisuallyHidden>
							{ __(
								'Updating payment methods information, please wait.',
								'woocommerce-gateway-stripe'
							) }
						</VisuallyHidden>
					) }
					<AccountRefreshingOverlay
						className={ classNames( {
							'has-overlay': isRefreshing,
						} ) }
					>
						<PaymentMethodsList
							isChangingDisplayOrder={ isChangingDisplayOrder }
							onSaveChanges={ onSaveChanges }
						/>
					</AccountRefreshingOverlay>
					{ isUpeEnabled && <SectionFooter /> }
				</LoadableSettingsSection>
			</StyledCard>
		</>
	);
};

export default GeneralSettingsSection;
