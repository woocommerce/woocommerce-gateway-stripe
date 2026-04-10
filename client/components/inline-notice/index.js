import React from 'react';
import classNames from 'classnames';
import { Notice } from '@wordpress/components';

import './style.scss';

const InlineNotice = ( { className, ...restProps } ) => (
	<Notice
		className={ classNames( 'wcstripe-inline-notice', className ) }
		{ ...restProps }
	/>
);

export default InlineNotice;
