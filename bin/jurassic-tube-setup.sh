#!/bin/bash

# Exit if any command fails.
set -e

echo "Checking if ${PWD}/docker/bin/jt directory exists..."

if [ -d "${PWD}/docker/bin/jt" ]; then
    echo "${PWD}/docker/bin/jt already exists."
else
    echo "Creating ${PWD}/docker/bin/jt directory..."
    mkdir -p "${PWD}/docker/bin/jt"
fi

echo "Downloading the latest version of the installer script..."
echo

# Download the installer (if it's not already present):
if [ ! -f "${PWD}/docker/bin/jt/installer.sh" ]; then
    # Download the installer script:
    curl "https://jurassic.tube/get-installer.php?env=wcpay" -o ${PWD}/docker/bin/jt/installer.sh && chmod +x ${PWD}/docker/bin/jt/installer.sh
fi

echo "Running the installation script..."
echo

# Run the installer script
source $PWD/docker/bin/jt/installer.sh

echo
read -p "Go to https://jurassic.tube/ in a browser, paste your public key which was printed above into the box, and click 'Add Public Key'. Press enter to continue"
echo

read -p "Go to https://jurassic.tube/ in a browser, add a subdomain using the desired name for your subdomain, and click 'Add Subdomain'. The subdomain name is what you will use to access WooCommerce Stripe Payment Gateway in a browser. When this is done, type the subdomain name here and press enter. Please just type in the subdomain, not the full URL: " subdomain
echo

read -p "Please enter your Automattic/WordPress.com username: " username
echo

${PWD}/docker/bin/jt/config.sh username ${username}
${PWD}/docker/bin/jt/config.sh subdomain ${subdomain}

echo "Setup complete!"
echo "Use the command: 'npm run jt:start' from the root directory of your WooCommerce Stripe Payment Gateway project to start running Jurassic Tube."
echo
