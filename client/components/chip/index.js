import React from 'react';
import classNames from 'classnames';
import './styles.scss';

/**
 * The Chip component.
 *
 * @param {Object} props              The component props.
 * @param {string} props.color        The color of the chip. Can be 'gray', 'green', 'blue', 'red', 'yellow'.
 * @param {string} props.text         The text of the chip.
 * @param {JSX.Element} props.icon    Optional icon for the chip.
 * @param {string} props.iconPosition The position of the icon. Default is 'right'. Can be 'left' or 'right'.
 * @return {JSX.Element}              The rendered Chip component.
 */
const Chip = ( { text, icon, color = 'gray', iconPosition = 'right' } ) => {
	return (
		<span
			className={ classNames(
				'wcstripe-chip',
				`wc-stripe-chip-${ color }`
			) }
		>
			{ iconPosition === 'left' && icon && <>{ icon }</> }
			{ text }
			{ iconPosition === 'right' && icon && <>{ icon }</> }
		</span>
	);
};

export default Chip;
