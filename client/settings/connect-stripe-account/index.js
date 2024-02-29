import { __ } from '@wordpress/i18n';
import { React, useState } from 'react';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import { Button, Card } from '@wordpress/components';
import CardBody from '../card-body';
import { AccountKeysModal } from '../payment-settings/account-keys-modal';
import StripeBanner from 'wcstripe/components/stripe-banner';
import { recordEvent } from 'wcstripe/tracking';

const CardWrapper = styled( Card )`
	max-width: 560px;

	h2 {
		font-size: 16px;
	}

	img {
		width: 100%;
	}
`;

const InformationText = styled.p`
	color: #1e1e1e;
`;

const TermsOfServiceText = styled.p`
	color: #757575;
	font-size: 12px;
	font-weight: 400;
	margin: 22px 0px 16px;
`;

const ButtonWrapper = styled.div`
	align-items: center;
	display: flex;
	flex-wrap: wrap;

	> :last-child {
		box-shadow: none;

		&:active:not( :disabled ),
		&:focus:not( :disabled ),
		&:hover:not( :disabled ) {
			box-shadow: none;
		}
	}
`;

const ConnectStripeAccount = ( { oauthUrl } ) => {
	// @todo - deconstruct modalType and setModalType from useModalType custom hook
	const [ modalType, setModalType ] = useState( '' );
	const handleModalDismiss = () => {
		setModalType( '' );
	};

	const handleCreateOrConnectAccount = () => {
		recordEvent( 'wcstripe_create_or_connect_account_click', {} );
		window.location.assign( oauthUrl );
	};

	const handleEnterAccountKeys = () => {
		recordEvent( 'wcstripe_enter_account_keys_click', {} );
		setModalType( 'live' );
	};

	return (
		<>
			{ modalType && (
				<AccountKeysModal
					type={ modalType }
					onClose={ handleModalDismiss }
					redirectOnSave={ `${ window.location.pathname }?page=wc-settings&tab=checkout&section=stripe&panel=settings` }
				/>
			) }
			<CardWrapper>
				<StripeBanner />
				<CardBody>
					<h2>
						{ __(
							'Get started with Stripe',
							'woocommerce-gateway-stripe'
						) }
					</h2>
					<InformationText>
						{ __(
							'Connect or create a Stripe account to accept payments directly onsite, including Payment Request buttons (such as Apple Pay and Google Pay), iDEAL, SEPA, and more international payment methods.',
							'woocommerce-gateway-stripe'
						) }
					</InformationText>

					{ oauthUrl && (
						<TermsOfServiceText>
							{ interpolateComponents( {
								mixedString: __(
									'By clicking "Create or connect an account", you agree to the {{tosLink}}Terms of service.{{/tosLink}}',
									'woocommerce-gateway-stripe'
								),
								components: {
									tosLink: (
										// eslint-disable-next-line jsx-a11y/anchor-has-content
										<a
											target="_blank"
											rel="noreferrer"
											href="https://wordpress.com/tos"
										/>
									),
								},
							} ) }
						</TermsOfServiceText>
					) }
					<ButtonWrapper>
						{ oauthUrl ? (
							<Button
								isPrimary
								onClick={ handleCreateOrConnectAccount }
							>
								{ __(
									'Create or connect an account',
									'woocommerce-gateway-stripe'
								) }
							</Button>
						) : (
							<Button
								isPrimary={ ! oauthUrl }
								isSecondary={ !! oauthUrl }
								onClick={ handleEnterAccountKeys }
							>
								{ __(
									'Enter account keys (advanced)',
									'woocommerce-gateway-stripe'
								) }
							</Button>
						) }
					</ButtonWrapper>
				</CardBody>
			</CardWrapper>
		</>
	);
};

export default ConnectStripeAccount;
