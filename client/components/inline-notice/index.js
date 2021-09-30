import React from 'react';
import { Notice } from '@wordpress/components';
import classNames from 'classnames';

import './style.scss';

const InlineNotice = ( { className, ...restProps } ) => (
	<Notice
		className={ classNames( 'wcstripe-inline-notice', className ) }
		{ ...restProps }
	/>
);

export default InlineNotice;
