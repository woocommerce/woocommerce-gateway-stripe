/**
 * External dependencies
 */
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import UpeToggleContext from '../upe-toggle/context';

const NoticeWrapper = styled( Notice )`
	background: #def1f7;
	border-left: 4px solid #00aadc;
	margin: 16px 0px;
	padding-right: 12px;

	&.is-dismissible {
		padding-right: 12px;
	}
`;

const CUSTOMIZATION_OPTIONS_NOTICE_OPTION =
	'wc_show_upe_customization_options_notice';

const CustomizationOptionNotice = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	const isCustomizationOptionsNoticeVisible = useSelect( ( select ) => {
		const { getOption, hasFinishedResolution } = select(
			'wc/admin/options'
		);

		const hasFinishedResolving = hasFinishedResolution( 'getOption', [
			CUSTOMIZATION_OPTIONS_NOTICE_OPTION,
		] );

		const isOptionDismissed =
			getOption( CUSTOMIZATION_OPTIONS_NOTICE_OPTION ) === 'no';

		return hasFinishedResolving && ! isOptionDismissed;
	} );

	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const handleDismissNotice = useCallback( () => {
		updateOptions( {
			[ CUSTOMIZATION_OPTIONS_NOTICE_OPTION ]: 'no',
		} );
	}, [ updateOptions ] );

	if ( ! isUpeEnabled || ! isCustomizationOptionsNoticeVisible ) {
		return null;
	}

	return (
		<NoticeWrapper isDismissible={ true } onRemove={ handleDismissNotice }>
			{ interpolateComponents( {
				mixedString: __(
					'{{strong}}Where are customization options?{{/strong}} In the new checkout experience, payment method details are automatically displayed in your customers’ languages so you don’t have to worry about writing them manually.',
					'woocommerce-gateway-stripe'
				),
				components: {
					strong: <b />,
				},
			} ) }
		</NoticeWrapper>
	);
};

export default CustomizationOptionNotice;
