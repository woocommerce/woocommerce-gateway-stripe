const fs = require( 'fs' );

/**
 * Adds a changelog entry to a file at a specific token position
 *
 * @param {Object} params Configuration parameters
 * @param {string} params.changelogEntry The changelog entry to add
 * @param {string} params.filename The file to add the entry to
 * @param {string} params.token The token to split the file on
 * @param {Object} params.core GitHub Actions core object (optional, for GitHub Actions environment)
 * @returns {boolean} True if successful, false if failed
 */
function addChangelogEntry( { changelogEntry, filename, token, core = null } ) {
	try {
		const changelog = fs.readFileSync( filename, 'utf-8' );
		const changelog_parts = changelog.split( token );

		if ( changelog_parts.length < 2 ) {
			throw new Error( 'Could not find changelog token in file' );
		}

		changelog_parts[ 1 ] =
			'\n' + changelogEntry.trim() + changelog_parts[ 1 ];
		const updatedChangelog = changelog_parts.join( token );
		fs.writeFileSync( filename, updatedChangelog );

		return true;
	} catch ( err ) {
		const errorMessage = `Unable to update changelog entries in ${ filename }: ${ err.message }`;
		if ( core ) {
			core.setFailed( errorMessage );
		} else {
			console.error( errorMessage );
		}
		return false;
	}
}

// Export for use in Node.js
if ( typeof module !== 'undefined' && module.exports ) {
	module.exports = { addChangelogEntry };
}

// Execute if running in GitHub Actions
if ( process.env.GITHUB_ACTIONS ) {
	const core = require( '@actions/core' );
	const changelogEntry = process.env.INPUT_CHANGELOG_ENTRY;
	const filename = process.env.INPUT_FILENAME;
	const token = 'xxxx-xx-xx =';

	addChangelogEntry( { changelogEntry, filename, token, core } );
}
