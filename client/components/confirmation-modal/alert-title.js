import React from 'react';
import { Icon, info } from '@wordpress/icons';
import './alert-title.scss';

const AlertTitle = ( { title } ) => (
	<span className="wcstripe-confirmation-modal__alert-title">
		<Icon
			icon={ info }
			className="wcstripe-confirmation-modal__alert-title-icon"
		/>
		{ title }
	</span>
);

export default AlertTitle;
