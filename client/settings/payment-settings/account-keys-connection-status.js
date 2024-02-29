import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { React } from 'react';
import styled from '@emotion/styled';
import GridIcon from 'gridicons';
import { useAccountKeys } from 'wcstripe/data/account-keys';

const SpanConnectionText = styled.span`
	margin-right: 0.3rem;

	@media ( min-width: 600px ) and ( max-width: 930px ) {
		max-width: 4.5rem;
	}
	@media ( max-width: 464px ) {
		max-width: 4.5rem;
	}
`;

const SpanConnectionLink = styled.span`
	color: var( --wp-admin-theme-color );
	cursor: pointer;
`;

const DivSpinner = styled.div`
	width: 1.25rem;
	height: 1.25rem;
	margin-right: 0.5rem;
	background-size: contain;
	background-image: url( 'data:image/gif;base64,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' );
`;

export const AccountKeysConnectionStatus = ( { formRef } ) => {
	const dispatch = useDispatch();
	const {
		isTesting,
		isValid,
		updateIsValidAccountKeys,
		testAccountKeys,
	} = useAccountKeys();

	const handleTestConnection = async ( ref ) => {
		// Grab the HTMLCollection of elements of the HTML form, convert to array.
		const elements = Array.from( ref.current.elements );
		// Convert HTML elements array to an object acceptable for saving keys.
		const keys = elements.reduce( ( dict, ele ) => {
			return Object.assign( dict, { [ ele.name ]: ele.value } );
		}, {} );

		// The form don't allow both LIVE and TEST keys to be updated at the same time.
		let data = {};
		if ( keys.publishable_key && keys.secret_key ) {
			if (
				! keys.publishable_key.startsWith( 'pk_live_' ) ||
				! (
					keys.secret_key.startsWith( 'sk_live_' ) ||
					keys.secret_key.startsWith( 'rk_live_' )
				)
			) {
				dispatch( 'core/notices' ).createErrorNotice(
					__(
						'Only live account keys should be entered.',
						'woocommerce-gateway-stripe'
					)
				);
				return;
			}
			data = {
				live: true,
				publishable: keys.publishable_key,
				secret: keys.secret_key,
			};
		} else if ( keys.test_publishable_key && keys.test_secret_key ) {
			if (
				! keys.test_publishable_key.startsWith( 'pk_test_' ) ||
				! (
					keys.test_secret_key.startsWith( 'sk_test_' ) ||
					keys.test_secret_key.startsWith( 'rk_test_' )
				)
			) {
				dispatch( 'core/notices' ).createErrorNotice(
					__(
						'Only test account keys should be entered.',
						'woocommerce-gateway-stripe'
					)
				);
				return;
			}
			data = {
				live: false,
				publishable: keys.test_publishable_key,
				secret: keys.test_secret_key,
			};
		}

		const testSuccess = await testAccountKeys( data );
		if ( testSuccess ) {
			updateIsValidAccountKeys( true );
		} else {
			updateIsValidAccountKeys( false );
		}
	};

	return (
		<div
			style={ {
				display: 'flex',
				flexDirection: 'column',
				justifyContent: 'center',
			} }
		>
			{ isTesting && (
				<div
					style={ {
						display: 'flex',
						flexDirection: 'row',
						alignItems: 'end',
					} }
				>
					<DivSpinner />
					<SpanConnectionText>
						{ __(
							'Testing connection…',
							'woocommerce-gateway-stripe'
						) }
					</SpanConnectionText>
				</div>
			) }
			{ ! isTesting && isValid === null && (
				<SpanConnectionLink
					onClick={ () => handleTestConnection( formRef ) }
				>
					{ __( 'Test connection', 'woocommerce-gateway-stripe' ) }
				</SpanConnectionLink>
			) }
			{ ! isTesting && isValid === true && (
				<div
					style={ {
						display: 'flex',
						flexDirection: 'row',
						alignItems: 'end',
					} }
				>
					<GridIcon
						icon="checkmark"
						size={ 18 }
						style={ { marginRight: '0.5rem', fill: '#4AB866' } }
					/>
					<SpanConnectionText>
						{ __(
							'Connection successful!',
							'woocommerce-gateway-stripe'
						) }
					</SpanConnectionText>
					<SpanConnectionLink
						onClick={ () => handleTestConnection( formRef ) }
					>
						{ __( 'Test again', 'woocommerce-gateway-stripe' ) }
					</SpanConnectionLink>
				</div>
			) }
			{ ! isTesting && isValid === false && (
				<div
					style={ {
						display: 'flex',
						flexDirection: 'row',
						alignItems: 'end',
					} }
				>
					<GridIcon
						icon="notice-outline"
						size={ 18 }
						style={ { marginRight: '0.5rem', fill: '#CC1818' } }
					/>
					<SpanConnectionText>
						{ __(
							"We couldn't connect.",
							'woocommerce-gateway-stripe'
						) }
					</SpanConnectionText>
					<SpanConnectionLink
						onClick={ () => handleTestConnection( formRef ) }
					>
						{ __( 'Try again', 'woocommerce-gateway-stripe' ) }
					</SpanConnectionLink>
				</div>
			) }
		</div>
	);
};
