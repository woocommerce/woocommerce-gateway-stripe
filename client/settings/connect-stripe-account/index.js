import { React, useState, useCallback } from 'react';
import styled from '@emotion/styled';
import interpolateComponents from '@automattic/interpolate-components';
import CardBody from '../card-body';
import { Card } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import StripeBanner from 'wcstripe/components/stripe-banner';
import ConnectButton from 'wcstripe/settings/stripe-auth-account/connect-button';
import ConnectionErrorNotice from 'wcstripe/settings/stripe-auth-account/connection-error-notice';

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

const ErrorContainer = styled.div`
	margin-bottom: 12px;
`;

const ButtonWrapper = styled.div`
	align-items: center;
	display: flex;
	flex-wrap: wrap;
	column-gap: 12px;

	> :last-child {
		box-shadow: none;

		&:active:not( :disabled ),
		&:focus:not( :disabled ),
		&:hover:not( :disabled ) {
			box-shadow: none;
		}
	}
`;

const ConnectStripeAccount = () => {
	const [ hasError, setHasError ] = useState( false );

	const handleErrorChange = useCallback(
		( error ) => {
			setHasError( !! error );
		},
		[ setHasError ]
	);

	return (
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
						'Connect or create a Stripe account to accept all major debit and credit cards, digital wallets (including Apple Pay and Google Pay), buy now, pay later options (such as Klarna and Affirm), and a wide range of local and international payment methods.',
						'woocommerce-gateway-stripe'
					) }
				</InformationText>
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
				<p className="woocommerce-stripe-auth__help">
					{ __(
						'Some payment methods are automatically enabled when you connect your account. Review your Payment Methods settings for details.',
						'woocommerce-gateway-stripe'
					) }
				</p>
				{ hasError && (
					<ErrorContainer>
						<ConnectionErrorNotice />
					</ErrorContainer>
				) }
				<ButtonWrapper>
					<ConnectButton
						testMode={ false }
						buttonVariant="primary"
						onErrorChange={ handleErrorChange }
					/>
					<ConnectButton
						testMode={ true }
						buttonVariant="secondary"
						onErrorChange={ handleErrorChange }
					/>
				</ButtonWrapper>
			</CardBody>
		</CardWrapper>
	);
};

export default ConnectStripeAccount;
