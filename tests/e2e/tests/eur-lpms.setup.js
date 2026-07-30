import { test as setup } from '@playwright/test';
import { admin } from '../utils';

setup( 'Configure store for EUR LPM tests', async ( { browser } ) => {
	// Change store currency to EUR. SEPA, iDEAL, and Bancontact are only
	// available for euro-denominated payments; the default Stripe account
	// supports them, so no account/key switch is needed.
	await admin.updateStoreCurrency( browser, 'EUR' );

	// Enable the EUR payment methods in the admin. SEPA Direct Debit is
	// listed as "Direct debit payment" in the admin methods panel.
	await admin.togglePaymentMethod( browser, 'Direct debit payment', true );
	await admin.togglePaymentMethod( browser, 'iDEAL | Wero', true );
	await admin.togglePaymentMethod( browser, 'Bancontact', true );
} );
