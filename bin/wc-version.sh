#!/usr/bin/env bash

# Maps a WooCommerce version selector to a download URL when WordPress.org cannot
# serve it.
#
# WordPress.org only hosts released tags, but the pre-release builds WooCommerce
# asks partners to test against exist solely as GitHub release assets: the rolling
# `nightly` build and the `X.Y.Z-dev` tags announced in the canonical-extensions
# testing posts. Passing one of those to `wp plugin install woocommerce --version=`
# resolves to a downloads.wordpress.org URL that 404s, so callers install the URL
# returned here positionally instead.
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
