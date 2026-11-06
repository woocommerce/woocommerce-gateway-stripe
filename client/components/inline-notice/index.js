import React from 'react';
import clsx from 'clsx';
import { Notice } from '@wordpress/components';

import './style.scss';

const InlineNotice = ( { className, ...restProps } ) => (
	<Notice
		className={ clsx( 'wcstripe-inline-notice', className ) }
		{ ...restProps }
	/>
);

export default InlineNotice;
