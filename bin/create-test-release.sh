#!/usr/bin/env bash

if [[ `dirname "$0"` != './bin' && `dirname "$0"` != 'bin' ]]; then
	echo "This script must be run from the root of the 'woocommerce-gateway-stripe' repo"
	exit 1
fi

if [ $# -lt 1 ]; then
	echo "Usage: $0 <version>"
	exit 1
fi

VERSION=$1

check_prerequisites() {
	command -v git >/dev/null 2>&1 || {
		echo "Git is not installed"
		exit 1
	}
	command -v php >/dev/null 2>&1 || {
	 	echo "PHP is not installed"
	 	exit 1
	}

	# Check for Woorelease phar file
	if [[ ! -f ".cache/woorelease.phar" ]]; then
		mkdir -p .cache

		echo 'Woorelease not found.'
		read -p "To download it, paste your GitHub token (need repo access): " -r
		if [[ -z "$REPLY" ]]; then
			echo ''
			echo 'You will need to download it from: https://github.com/woocommerce/woorelease/releases/latest, and extract it into .cache/ manually'
			exit 0
		fi

		github_token=$REPLY

		response=$(curl -s --header "Authorization: token $github_token" https://api.github.com/repos/woocommerce/woorelease/releases/latest)
		download_url=$(echo "$response" | php -r '$assets = json_decode( file_get_contents( "php://stdin" ) )->assets; foreach ( $assets as $asset ) { if ( $asset->name === "woorelease.zip") { $url = $asset->url; break; } }; print_r( $url );')
        if [[ -z "$download_url" ]]; then
			echo "Unable to download Woorelease, verify your GitHub token"
			echo 'Download it from: https://github.com/woocommerce/woorelease/releases/latest, and extract it into the .cache/ folder manually'
			exit 1
        fi

		http_code=$(curl -sL --header "Authorization: token $github_token" --header 'Accept: application/octet-stream' $download_url --output .cache/woorelease.zip --write-out "%{http_code}")
		if [[ ${http_code} -lt 200 || ${http_code} -gt 299 ]]; then
            echo "Unable to download Woorelease zip package (status_code: $http_code)"
			echo 'Download it from: https://github.com/woocommerce/woorelease/releases/latest, and extract it into the .cache/ folder manually'
			exit 1
		fi

		unzip .cache/woorelease.zip -d .cache >/dev/null
		rm .cache/woorelease.zip
	fi

	# Check for Woorelease config file
	if [[ ! -f ~/.woorelease/config ]]; then
	 	echo 'Woorelease config file not found (~/.woorelease/config).'
		echo 'Please follow the configuration steps here: https://github.com/woocommerce/woorelease#prerequisites-for-configuration'
	 	exit 1
	fi
}

print_summary() {
	echo ''
	echo 'This script will perform the following actions:'
	echo "  1. Checkout the branch 'develop' and pull the latest changes"
	echo "  2. Create a new branch named 'release/$VERSION' using 'develop' as base, and push it to GitHub"
	echo "  3. Create a new tag named '$VERSION-test', and push it to GitHub."
	echo "  4. Run Woorelease simulation locally with version=$VERSION (using the newly created branch)"
}

create_git_branch() {
	# Create GitHub branch and tag for the test version
	echo ''
	echo "> git checkout develop && git pull"
	git checkout develop && git pull
	[ $? -eq 0 ] || exit 1

	echo ''
	echo "> git checkout -b release/$VERSION"
	git checkout -b release/$VERSION
	[ $? -eq 0 ] || exit 1

	echo ''
	echo "> git push origin release/$VERSION"
	git push origin release/$VERSION
	[ $? -eq 0 ] || exit 1

	echo ''
	echo "> git tag $VERSION-test && git push origin $VERSION-test"
	git tag $VERSION-test && git push origin $VERSION-test
	[ $? -eq 0 ] || exit 1
}

create_test_package() {
	# We simulate creating the release with the version number VERSION (ex: 5.3.0) and not the current test version (ex: 5.3.0-test)
	echo ''
	echo "> php .cache/woorelease.phar simulate --product_version=$VERSION https://github.com/woocommerce/woocommerce-gateway-stripe/tree/release/$VERSION"
	output=$(php .cache/woorelease.phar simulate --product_version=$VERSION https://github.com/woocommerce/woocommerce-gateway-stripe/tree/release/$VERSION | tee /dev/stderr )

	# Update the 'Version' tag in woocommerce-gateway-stripe.php to the current version + '-test'
	ZIP_FILE=$(echo $output | sed -n "s/.*Skipping upload of asset \(.*\) to GH release.*/\1/p")
	[ $? -eq 0 ] || exit 1

	cd $(echo "$ZIP_FILE" | rev | cut -d '/' -f3- | rev)
	sed -i '' "s/^ \* Version: .*$/ * Version: $VERSION-test/" woocommerce-gateway-stripe/woocommerce-gateway-stripe.php
	zip --update woocommerce-gateway-stripe/woocommerce-gateway-stripe.zip woocommerce-gateway-stripe/woocommerce-gateway-stripe.php
	cd -
	cp $ZIP_FILE .
}

# -----

check_prerequisites;
print_summary;
echo ''
read -p "Do you want to continue? (yes/no) " -n 1 -r
echo ''
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
	exit 0
fi

create_git_branch;
create_test_package;

echo ''
echo "Test release $VERSION generated successfully."
echo ''
echo "You need to upload the zip package 'woocommerce-gateway-stripe.zip' to the tag '$VERSION-test' on GitHub (creating a version and marking it as pre-release BEFORE saving)."
