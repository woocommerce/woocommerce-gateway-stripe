import { __ } from '@wordpress/i18n';
import { React } from 'react';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import { Button, Card, ExternalLink } from '@wordpress/components';
import CardBody from '../card-body';
import StripeBanner from 'wcstripe/components/stripe-banner';
import { recordEvent } from 'wcstripe/tracking';
import InlineNotice from 'wcstripe/components/inline-notice';

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

const ConnectStripeAccount = ( { oauthUrl, testOauthUrl } ) => {
	const handleCreateOrConnectAccount = () => {
		recordEvent( 'wcstripe_create_or_connect_account_click', {} );
		window.location.assign( oauthUrl );
	};

	const handleCreateOrConnectTestAccount = () => {
		recordEvent( 'wcstripe_create_or_connect_test_account_click', {} );
		window.location.assign( testOauthUrl );
	};

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
						'Connect or create a Stripe account to accept payments directly onsite, including Payment Request buttons (such as Apple Pay and Google Pay), iDEAL, SEPA, and more international payment methods.',
						'woocommerce-gateway-stripe'
					) }
				</InformationText>

				{ oauthUrl || testOauthUrl ? (
					<>
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
						<ButtonWrapper>
							{ oauthUrl && (
								<Button
									variant="primary"
									onClick={ handleCreateOrConnectAccount }
								>
									{ __(
										'Create or connect an account',
										'woocommerce-gateway-stripe'
									) }
								</Button>
							) }
							{ testOauthUrl && (
								<Button
									variant={
										oauthUrl ? 'secondary' : 'primary'
									}
									onClick={ handleCreateOrConnectTestAccount }
								>
									{ oauthUrl
										? __(
												'Create or connect a test account instead',
												'woocommerce-gateway-stripe'
										  )
										: __(
												'Create or connect a test account',
												'woocommerce-gateway-stripe'
										  ) }
								</Button>
							) }
						</ButtonWrapper>
					</>
				) : (
					<InlineNotice isDismissible={ false } status="error">
						{ interpolateComponents( {
							mixedString: __(
								'An issue occurred generating a connection to Stripe. Please try again. For more assistance, refer to our {{Link}}documentation{{/Link}}.',
								'woocommerce-gateway-stripe'
							),
							components: {
								Link: (
									<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/connecting-to-stripe/" />
								),
							},
						} ) }
					</InlineNotice>
				) }
			</CardBody>
		</CardWrapper>
	);
};

export default ConnectStripeAccount;
