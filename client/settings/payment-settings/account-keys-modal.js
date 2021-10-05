import { __ } from '@wordpress/i18n';
import { React } from 'react';
import { Button, TextControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { useTestMode } from 'wcstripe/data';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import InlineNotice from 'wcstripe/components/inline-notice';

export const AccountKeysModal = ( { type, onClose } ) => {
	const [ , setTestMode ] = useTestMode();

	const handleSave = () => {
		setTestMode( type === 'test' );
		onClose();
	};

	return (
		<ConfirmationModal
			onRequestClose={ onClose }
			actions={
				<>
					<Button isSecondary onClick={ onClose }>
						{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
					</Button>
					<Button isPrimary onClick={ handleSave }>
						{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
					</Button>
				</>
			}
			title={
				type === 'test'
					? __(
							'Edit test account keys & webhooks',
							'woocommerce-gateway-stripe'
					  )
					: __(
							'Edit live account keys & webhooks',
							'woocommerce-gateway-stripe'
					  )
			}
		>
			<InlineNotice isDismissable={ false }>
				{ type === 'test'
					? interpolateComponents( {
							mixedString: __(
								"To enable the test mode, get the test account keys from your {{accountLink}}Stripe Account{{/accountLink}} (we'll save them for you so you won't have to do this every time).",
								'woocommerce-gateway-stripe'
							),
							components: {
								accountLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a href="https://dashboard.stripe.com/test/apikeys" />
								),
							},
					  } )
					: interpolateComponents( {
							mixedString: __(
								"To enable the live mode, get the account keys from your {{accountLink}}Stripe Account{{/accountLink}} (we'll save them for you so you won't have to do this every time).",
								'woocommerce-gateway-stripe'
							),
							components: {
								accountLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a href="https://dashboard.stripe.com/apikeys" />
								),
							},
					  } ) }
			</InlineNotice>
			<TextControl
				label={
					type === 'test'
						? __(
								'Test publishable key',
								'woocommerce-gateway-stripe'
						  )
						: __(
								'Live publishable key',
								'woocommerce-gateway-stripe'
						  )
				}
				help={
					type === 'test'
						? __(
								'Only values starting with "pk_test_" will be saved.',
								'woocommerce-gateway-stripe'
						  )
						: __(
								'Only values starting with "pk_live_" will be saved.',
								'woocommerce-gateway-stripe'
						  )
				}
			/>
			<TextControl
				label={
					type === 'test'
						? __( 'Test secret key', 'woocommerce-gateway-stripe' )
						: __( 'Live secret key', 'woocommerce-gateway-stripe' )
				}
				help={
					type === 'test'
						? __(
								'Only values starting with "sk_test_" or "rk_test_" will be saved.',
								'woocommerce-gateway-stripe'
						  )
						: __(
								'Only values starting with "sk_live_" or "rk_live_" will be saved.',
								'woocommerce-gateway-stripe'
						  )
				}
			/>
			<TextControl
				label={ __( 'Webhook secret', 'woocommerce-gateway-stripe' ) }
				help={ __(
					'Get your webhook signing secret from the webhooks section in your Stripe account.',
					'woocommerce-gateway-stripe'
				) }
			/>
		</ConfirmationModal>
	);
};
