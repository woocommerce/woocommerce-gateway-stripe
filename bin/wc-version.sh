#!/usr/bin/env bash

# Maps a WooCommerce version selector to a download URL when WordPress.org cannot
# serve it.
#
# WordPress.org only hosts released tags, but we need to be able to run tests
# against development versions of WooCommerce that are hosted on GitHub.
# This function returns a URL when the plugin zip needs to be downloaded
# from GitHub, as it won't be available from WordPress.org.
#
# Echoes an empty string for every selector WordPress.org does serve, including
# explicit beta/RC tags such as 11.2.0-beta.1, which are published there.
resolve_wc_download_url() {
	local version=$1

	if [[ $version == 'nightly' || $version == 'trunk' ]]; then
		echo "https://github.com/woocommerce/woocommerce/releases/download/nightly/woocommerce-trunk-nightly.zip"
		return
	fi

	if [[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+-dev$ ]]; then
		echo "https://github.com/woocommerce/woocommerce/releases/download/${version}/woocommerce.zip"
		return
	fi

	echo ''
}
