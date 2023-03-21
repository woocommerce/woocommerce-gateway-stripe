import * as dotenv from 'dotenv';
import axios from 'axios';
import fs from 'fs';
import path from 'path';

dotenv.config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const { GITHUB_TOKEN } = process.env;

const getReleaseInfo = async ( { repo, releaseTag } ) => {
	const options = {
		url: `https://api.github.com/repos/${ repo }/releases/${
			releaseTag === 'latest' ? '' : 'tags/'
		}${ releaseTag }`,
		headers: {
			Authorization: `token ${ GITHUB_TOKEN }`,
		},
	};

	// Make a request to the GitHub API to get information about the release
	const releaseInfo = await axios( options ).catch( ( { response } ) => {
		console.error(
			`GitHub API request failed: [${ response.status }] ${ response.data.message }`
		);
		process.exit( 1 );
	} );

	// Return the release information
	return releaseInfo.data;
};

export const downloadRelease = async ( {
	repo,
	releaseTag,
	filename,
	downloadPath,
} ) => {
	// Create destination folder.
	const dir = path.dirname( downloadPath );
	fs.mkdirSync( dir, { recursive: true } );

	// Get the release information
	const release = await getReleaseInfo( { repo, releaseTag } );

	// Find the asset that has the specified filename
	const asset = release.assets.find( ( asset ) => asset.name === filename );

	if ( ! asset ) {
		throw new Error(
			`Asset ${ filename } not found in release ${ releaseTag } of repository ${ repo }`
		);
	}

	// Download the asset
	const options = {
		method: 'GET',
		url: asset.url,
		responseType: 'stream',
		headers: {
			Authorization: `token ${ GITHUB_TOKEN }`,
			Accept: 'application/octet-stream',
		},
	};

	const response = await axios( options );

	// Return the response data as a stream
	return response.data.pipe( fs.createWriteStream( downloadPath ) );
};
