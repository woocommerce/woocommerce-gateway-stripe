import React from 'react';
import { Modal } from '@wordpress/components';
import classNames from 'classnames';
import { HorizontalRule } from '@wordpress/primitives';

import './style.scss';

const ConfirmationModal = ( { children, actions, className, ...props } ) => (
	<Modal
		className={ classNames( 'wcstripe-confirmation-modal', className ) }
		{ ...props }
	>
		{ children }
		{ actions && (
			<>
				<HorizontalRule className="wcstripe-confirmation-modal__separator" />
				<div className="wcstripe-confirmation-modal__footer">
					{ actions }
				</div>
			</>
		) }
	</Modal>
);

export default ConfirmationModal;
