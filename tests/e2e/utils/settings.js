import config from 'config';

const baseUrl = config.get( 'url' );

const UPE_SETTINGS_PAGE =
	baseUrl + 'wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe';

export const StripeSettings = {
	openUpeSettingsPage: async () => {
		await page.goto( UPE_SETTINGS_PAGE, {
			waitUntil: 'networkidle0',
		} );
	},
};
