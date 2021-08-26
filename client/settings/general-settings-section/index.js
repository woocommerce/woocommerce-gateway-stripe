/**
 * External dependencies
 */
import React from 'react';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import SofortIcon from '../../payment-method-icons/sofort';
import SepaIcon from '../../payment-method-icons/sepa';
import CardsIcon from '../../payment-method-icons/cards';
import GiropayIcon from '../../payment-method-icons/giropay';
import ApplePayIcon from '../../payment-method-icons/apple-pay';
import GooglePayIcon from '../../payment-method-icons/google-pay';

const GeneralSettingsSection = () => {
	return (
		<Card>
			<CardBody>
				The general settings sections goes here.
				<ul>
					<li>
						<GooglePayIcon />
						<GooglePayIcon size="medium" />
					</li>
					<li>
						<ApplePayIcon />
						<ApplePayIcon size="medium" />
					</li>
					<li>
						<SofortIcon />
						<SofortIcon size="medium" />
					</li>
					<li>
						<GiropayIcon />
						<GiropayIcon size="medium" />
					</li>
					<li>
						<SepaIcon />
						<SepaIcon size="medium" />
					</li>
					<li>
						<CardsIcon />
						<CardsIcon size="medium" />
					</li>
				</ul>
			</CardBody>
		</Card>
	);
};

export default GeneralSettingsSection;
