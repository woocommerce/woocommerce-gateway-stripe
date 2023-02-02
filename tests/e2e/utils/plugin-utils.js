import { APIRequest } from '@playwright/test';
import axios from 'axios';
import fs from 'fs';
import path from 'path';

/**
 * Encode basic auth username and password to be used in HTTP Authorization header.
 *
 * @param {string} username
 * @param {string} password
 * @returns Base64-encoded string
 */
const encodeCredentials = ( username, password ) => {
	return Buffer.from( `${ username }:${ password }` ).toString( 'base64' );
};

/**
 * Deactivate and delete a plugin specified by the given `slug` using the WordPress API.
 *
 * @param {object} params
 * @param {APIRequest} params.request
 * @param {string} params.baseURL
 * @param {string} params.slug
 * @param {string} params.username
 * @param {string} params.password
 */
export const deletePlugin = async ( {
	request,
	baseURL,
	slug,
	username,
	password,
} ) => {
	// Check if plugin is installed by getting the list of installed plugins, and then finding the one whose `textdomain` property equals `slug`.
	const apiContext = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			Authorization: `Basic ${ encodeCredentials( username, password ) }`,
		},
	} );
	const listPluginsResponse = await apiContext.get(
		`/wp-json/wp/v2/plugins`,
		{
			failOnStatusCode: true,
		}
	);
	const pluginsList = await listPluginsResponse.json();
	const pluginToDelete = pluginsList.find(
		( { textdomain } ) => textdomain === slug
	);

	// If installed, get its `plugin` value and use it to deactivate and delete it.
	if ( pluginToDelete ) {
		const { plugin } = pluginToDelete;

		await apiContext.put( `/wp-json/wp/v2/plugins/${ plugin }`, {
			data: { status: 'inactive' },
			failOnStatusCode: true,
		} );

		await apiContext.delete( `/wp-json/wp/v2/plugins/${ plugin }`, {
			failOnStatusCode: true,
		} );
	}
};

/**
 * Download the zip file from a remote location.
 *
 * @param {object} param
 * @param {string} param.url The URL where the zip file is located.
 * @param {string} param.downloadPath The location where to download the zip to.
 * @param {string} param.authToken Authorization token used to authenticate with the GitHub API if required.
 */
export const downloadZip = async ( { url, downloadPath, authToken } ) => {
	// Create destination folder.
	const dir = path.dirname( downloadPath );
	fs.mkdirSync( dir, { recursive: true } );

	// Download the zip.
	const options = {
		url,
		responseType: 'stream',
		headers: {
			'user-agent': 'node.js',
		},
	};

	// If provided with a token, use it for authorization
	if ( authToken ) {
		options.headers.Authorization = `token ${ authToken }`;
	}

	const response = await axios( options );
	response.data.pipe( fs.createWriteStream( downloadPath ) );
};

/**
 * Delete a zip file. Useful when cleaning up downloaded plugin zips.
 *
 * @param {string} zipFilePath Local file path to the ZIP.
 */
export const deleteZip = async ( zipFilePath ) => {
	console.log( `- Deleting file located in ${ zipFilePath }...` );
	await fs.unlink( zipFilePath, ( err ) => {
		if ( err ) throw err;
	} );
	console.log( `\u2714 Successfully deleted!` );
};

/**
 * Get the download URL of the release zip from GitHub
 *
 * @param string version The version to be tested.
 * @return string Download URL for the release zip file.
 */
export const getReleaseZipUrl = async ( version ) => {
	return `https://github.com/woocommerce/woocommerce-gateway-stripe/releases/download/${ version }/woocommerce-gateway-stripe.zip`;
};

/**
 * Use the {@link https://developer.wordpress.org/rest-api/reference/plugins/#create-a-plugin Create plugin endpoint} to install and activate a plugin.
 *
 * @param {object} params
 * @param {APIRequest} params.request
 * @param {string} params.baseURL
 * @param {string} params.slug
 * @param {string} params.username
 * @param {string} params.password
 */
export const createPlugin = async ( {
	request,
	baseURL,
	slug,
	username,
	password,
} ) => {
	const apiContext = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			Authorization: `Basic ${ encodeCredentials( username, password ) }`,
		},
	} );

	await apiContext.post( '/wp-json/wp/v2/plugins', {
		data: { slug, status: 'active' },
		failOnStatusCode: true,
	} );
};
