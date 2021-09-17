import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import styled from '@emotion/styled';
import React, { useContext } from 'react';
import interpolateComponents from 'interpolate-components';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';
import InlineNotice from '../../components/inline-notice';
import UpeToggleContext from '../upe-toggle/context';

const NoticeWrapper = styled( InlineNotice )`
	// increasing the specificity to override the margin value.
	&& {
		margin-bottom: 0;
	}
`;

const CUSTOMIZATION_OPTIONS_NOTICE_OPTION =
	'wc_show_upe_customization_options_notice';

const CustomizationOptionsNotice = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	const isCustomizationOptionsNoticeVisible = useSelect( ( select ) => {
		const { getOption, hasFinishedResolution } = select(
			OPTIONS_STORE_NAME
		);

		const hasFinishedResolving = hasFinishedResolution( 'getOption', [
			CUSTOMIZATION_OPTIONS_NOTICE_OPTION,
		] );

		const isOptionDismissed =
			getOption( CUSTOMIZATION_OPTIONS_NOTICE_OPTION ) === 'no';

		return hasFinishedResolving && ! isOptionDismissed;
	} );

	const { updateOptions } = useDispatch( OPTIONS_STORE_NAME );

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
					'{{strong}}Where are the customization options?{{/strong}} In the new checkout experience, payment method details are automatically displayed in your customers’ languages so you don’t have to worry about writing them manually.',
					'woocommerce-gateway-stripe'
				),
				components: {
					strong: <b />,
				},
			} ) }
		</NoticeWrapper>
	);
};

export default CustomizationOptionsNotice;
