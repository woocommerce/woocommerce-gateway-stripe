#!/usr/bin/env node
/**
 * Compiles the entry files in changelog/ into changelog.txt and readme.txt.
 *
 * Run on the release branch ahead of a release. The entry files are consumed
 * (deleted) and a section for the given version is written to both files with
 * a placeholder date, which the release tooling replaces with the real one.
 * Running it again for the same version, after more entries have landed,
 * appends them to that section.
 *
 * Usage:
 *   npm run changelog:build-file -- --version=X.Y.Z [--dry-run]
 */
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

// The release tooling dates a release by replacing the first header that carries this placeholder,
// and fails when there is none, so compiled sections must be written with it rather than a real date.
const UNRELEASED_DATE = 'xxxx-xx-xx';
const CHANGELOG_HEADING = /^(\S+) - version (\S+)\s*$/;
const README_CHANGELOG_HEADING = '== Changelog ==';
const README_FULL_HISTORY_LINK = /^\[See changelog for full details/;
const KEEP_FILE = '.gitkeep';

/**
 * @param {string} changesDir Directory holding the entry files.
 * @return {string[]} Entry file names. Dotfiles are not entries; .gitkeep keeps the directory in git.
 */
function listEntryFiles( changesDir ) {
	return fs
		.readdirSync( changesDir )
		.filter( ( name ) => ! name.startsWith( '.' ) )
		.sort();
}

/**
 * @param {string} content Entry file contents: `Header: value` lines, a blank line, then the entry text.
 * @return {{type: string, entry: string}} The entry's type key and text.
 */
function parseEntryFile( content ) {
	const [ head, ...rest ] = content.replace( /\r\n/g, '\n' ).split( '\n\n' );
	const type = ( head.match( /^Type:\s*(.+)$/im ) || [] )[ 1 ];
	return {
		type: type ? type.trim().toLowerCase() : '',
		entry: rest.join( '\n\n' ).trim(),
	};
}

/**
 * @param {string} label Type label, e.g. `Fix`.
 * @param {string} entry Entry text.
 * @return {string} The line as the formatter writes it to changelog.txt.
 */
function renderLine( label, entry ) {
	return `* ${ label } - ${ entry.trim().replace( /\.$/, '' ) }`;
}

/**
 * Finds entry files whose line is already in the changelog.
 *
 * A fix cherry-picked onto a release branch is compiled and its entry file deleted there, but
 * the original entry file on the development branch survives the merge back, because both
 * branches added it independently. Without this it would be listed again in the next version.
 *
 * @param {{name: string, type: string, entry: string}[]} entries       Parsed entry files.
 * @param {Object<string, string>}                        typeLabels    Type key to label.
 * @param {string}                                        changelogText Contents of changelog.txt.
 * @return {string[]} Names of the entry files already present in the changelog.
 */
function findDuplicateEntries( entries, typeLabels, changelogText ) {
	const existing = new Set(
		changelogText.split( '\n' ).map( ( line ) => line.trim() )
	);
	return entries
		.filter(
			( { type, entry } ) =>
				entry &&
				typeLabels[ type ] &&
				existing.has( renderLine( typeLabels[ type ], entry ) )
		)
		.map( ( { name } ) => name );
}

/**
 * @param {string} changelogText Contents of changelog.txt.
 * @return {{date: string, version: string, lines: string[]}|null} The newest version section.
 */
function getTopSection( changelogText ) {
	const lines = changelogText.split( '\n' );
	const start = lines.findIndex( ( line ) => CHANGELOG_HEADING.test( line ) );
	if ( start === -1 ) {
		return null;
	}
	const [ , date, version ] = lines[ start ].match( CHANGELOG_HEADING );
	let end = lines.findIndex(
		( line, index ) => index > start && CHANGELOG_HEADING.test( line )
	);
	if ( end === -1 ) {
		end = lines.length;
	}
	const body = lines.slice( start + 1, end );
	while ( body.length && body[ body.length - 1 ].trim() === '' ) {
		body.pop();
	}
	return { date, version, lines: body };
}

/**
 * @param {string} changelogText Contents of changelog.txt.
 * @param {number} skip          Number of leading version sections to leave out.
 * @return {string} The changelog from the version section after the skipped ones to the end.
 */
function getHistory( changelogText, skip ) {
	const lines = changelogText.split( '\n' );
	const headings = lines
		.map( ( line, index ) =>
			CHANGELOG_HEADING.test( line ) ? index : -1
		)
		.filter( ( index ) => index !== -1 );
	return skip < headings.length
		? lines.slice( headings[ skip ] ).join( '\n' )
		: '';
}

/**
 * Replaces the readme's changelog with a single version section. readme.txt only carries the
 * current release; changelog.txt holds the full history and is the source for this section.
 *
 * @param {string}                                           readmeText Contents of readme.txt.
 * @param {{date: string, version: string, lines: string[]}} section    Section to write.
 * @return {string} Updated readme contents.
 */
function syncReadme( readmeText, section ) {
	const lines = readmeText.split( '\n' );
	const start = lines.findIndex(
		( line ) => line.trim() === README_CHANGELOG_HEADING
	);
	const end = lines.findIndex(
		( line, index ) =>
			index > start && README_FULL_HISTORY_LINK.test( line )
	);
	if ( start === -1 || end === -1 ) {
		throw new Error(
			`readme.txt must contain a "${ README_CHANGELOG_HEADING }" heading followed by the "[See changelog for full details" link`
		);
	}
	return [
		...lines.slice( 0, start + 1 ),
		'',
		`= ${ section.version } - ${ section.date } =`,
		...section.lines,
		'',
		...lines.slice( end ),
	].join( '\n' );
}

/**
 * Decides how the version section is written, and rejects states the release tooling cannot handle.
 *
 * @param {{date: string, version: string}|null} topSection Newest section in changelog.txt.
 * @param {string}                               version    Version being compiled.
 * @return {boolean} Whether to append to the existing top section instead of adding a new one.
 */
function shouldAmend( topSection, version ) {
	if ( ! topSection || topSection.date !== UNRELEASED_DATE ) {
		return false;
	}
	if ( topSection.version !== version ) {
		// Only the first placeholder header gets dated at release, so a second one would ship undated.
		throw new Error(
			`changelog.txt already has an unreleased section for ${ topSection.version }. Release it, or compile with --version=${ topSection.version }.`
		);
	}
	return true;
}

/**
 * @param {string} changesDir Directory holding the entry files.
 */
function ensureKeepFile( changesDir ) {
	const keepFile = path.join( changesDir, KEEP_FILE );
	if ( ! fs.existsSync( keepFile ) ) {
		fs.writeFileSync( keepFile, '' );
	}
}

/**
 * @param {string[]} argv Command-line arguments.
 * @return {{version: string, dryRun: boolean}} Parsed options.
 */
function parseArgs( argv ) {
	const options = { version: '', dryRun: false };
	for ( const arg of argv ) {
		const version = arg.match( /^--version=(.*)$/ );
		if ( version ) {
			options.version = version[ 1 ];
		} else if ( arg === '--dry-run' ) {
			options.dryRun = true;
		} else {
			throw new Error( `Unknown argument: ${ arg }` );
		}
	}
	if ( ! /^\d+\.\d+\.\d+$/.test( options.version ) ) {
		throw new Error(
			'Usage: npm run changelog:build-file -- --version=X.Y.Z [--dry-run]'
		);
	}
	return options;
}

/**
 * @param {string}   root Repository root.
 * @param {string[]} argv Command-line arguments.
 */
function run( root, argv ) {
	const { version, dryRun } = parseArgs( argv );
	const changelogger = path.join( root, 'vendor', 'bin', 'changelogger' );
	if ( ! fs.existsSync( changelogger ) ) {
		throw new Error(
			'vendor/bin/changelogger not found. Run `composer install` first.'
		);
	}

	const changesDir = path.join( root, 'changelog' );
	const changelogPath = path.join( root, 'changelog.txt' );
	const changelogText = fs.readFileSync( changelogPath, 'utf8' );

	const typeLabels = JSON.parse(
		fs.readFileSync( path.join( root, 'composer.json' ), 'utf8' )
	).extra.changelogger.types;

	const entries = listEntryFiles( changesDir ).map( ( name ) => ( {
		name,
		...parseEntryFile(
			fs.readFileSync( path.join( changesDir, name ), 'utf8' )
		),
	} ) );
	const duplicates = new Set(
		findDuplicateEntries( entries, typeLabels, changelogText )
	);
	for ( const name of duplicates ) {
		// eslint-disable-next-line no-console
		console.warn(
			`Skipping changelog/${ name }: its entry is already in changelog.txt. Removing the file.`
		);
		if ( ! dryRun ) {
			fs.unlinkSync( path.join( changesDir, name ) );
		}
	}

	const pending = entries.filter( ( { name } ) => ! duplicates.has( name ) );
	if ( ! pending.length ) {
		// eslint-disable-next-line no-console
		console.log( 'No changelog entries to compile.' );
		return;
	}

	const amend = shouldAmend( getTopSection( changelogText ), version );
	if ( dryRun ) {
		// eslint-disable-next-line no-console
		console.log(
			`Would ${
				amend ? 'append to' : 'add'
			} the ${ version } section with:`
		);
		pending.forEach( ( { type, entry } ) =>
			// eslint-disable-next-line no-console
			console.log( renderLine( typeLabels[ type ] || type, entry ) )
		);
		return;
	}

	const args = [
		'write',
		'--no-interaction',
		`--use-version=${ version }`,
		'--release-date=unreleased',
	];
	if ( amend ) {
		args.push( '--amend' );
	}
	execFileSync( changelogger, args, { cwd: root, stdio: 'inherit' } );
	ensureKeepFile( changesDir );

	// The changelogger re-renders the whole file, so confirm that only the compiled section changed.
	const compiledText = fs.readFileSync( changelogPath, 'utf8' );
	if (
		getHistory( compiledText, 1 ) !==
		getHistory( changelogText, amend ? 1 : 0 )
	) {
		throw new Error(
			'Compiling changed earlier sections of changelog.txt. Restore with `git checkout -- changelog.txt changelog` and check the changelog formatter.'
		);
	}

	const readmePath = path.join( root, 'readme.txt' );
	const section = getTopSection( compiledText );
	fs.writeFileSync(
		readmePath,
		syncReadme( fs.readFileSync( readmePath, 'utf8' ), section )
	);

	// eslint-disable-next-line no-console
	console.log(
		`Compiled ${ pending.length } ${
			pending.length === 1 ? 'entry' : 'entries'
		} into the ${ version } section of changelog.txt and readme.txt.`
	);
	// eslint-disable-next-line no-console
	console.log(
		'The readme.txt section is a flat list; run the Format Release Notes workflow to regroup it.'
	);
}

if ( require.main === module ) {
	try {
		run( path.resolve( __dirname, '..' ), process.argv.slice( 2 ) );
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error( `Error: ${ error.message }` );
		process.exit( 1 );
	}
}

module.exports = {
	listEntryFiles,
	parseEntryFile,
	renderLine,
	findDuplicateEntries,
	getTopSection,
	getHistory,
	syncReadme,
	shouldAmend,
	ensureKeepFile,
	parseArgs,
};
