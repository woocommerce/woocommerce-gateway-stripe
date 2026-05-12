import React from 'react';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';

const DiagnosticsTraceViewModal = ( { trace, onClose, onCopy } ) => {
	if ( ! trace ) {
		return null;
	}

	const json = JSON.stringify( trace, null, 2 );

	return (
		<ConfirmationModal
			className="wc-stripe-diagnostics-view-modal"
			onRequestClose={ onClose }
			title={ sprintf(
				/* translators: %s: trace id */
				__( 'Trace %s', 'woocommerce-gateway-stripe' ),
				trace.id
			) }
			actions={
				<>
					<Button onClick={ onClose } isSecondary>
						{ __( 'Close', 'woocommerce-gateway-stripe' ) }
					</Button>
					<Button onClick={ () => onCopy( trace ) } isPrimary>
						{ __( 'Copy trace', 'woocommerce-gateway-stripe' ) }
					</Button>
				</>
			}
		>
			<pre className="wc-stripe-diagnostics-view-modal__json">
				{ json }
			</pre>
		</ConfirmationModal>
	);
};

export default DiagnosticsTraceViewModal;
