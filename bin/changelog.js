#!/usr/bin/env node
/**
 * Adds a changelog entry file under changelog/ using the Jetpack Changelogger.
 *
 * Usage:
 *   npm run changelog                                      Interactive prompts.
 *   npm run changelog -- --type=fix --entry="Message"      Non-interactive.
 *
 * Entries carry no version: they are compiled into changelog.txt and readme.txt
 * on the release branch by `npm run changelog:build-file`.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { execFile, spawn } = require( 'child_process' );
const { promisify } = require( 'util' );
const readline = require( 'readline' );
const inquirer = require( 'inquirer' );

const execFileP = promisify( execFile );

const CHANGE_TYPES = {
	Fix: 'Fixes an existing bug',
	Add: 'Adds functionality',
	Update: 'Update existing functionality',
	Remove: 'Removes existing functionality',
	Dev: 'Development related task',
	Tweak: 'A minor adjustment to the codebase',
};

const CLAUDE_SUGGEST = '__claude_suggest__';
const BASE_BRANCH = 'develop';

const REPO_ROOT = path.resolve( __dirname, '..' );
const CHANGES_DIR = path.join( REPO_ROOT, 'changelog' );
const CHANGELOGGER = path.join( REPO_ROOT, 'vendor', 'bin', 'changelogger' );

const listEntryFiles = () =>
	fs
		.readdirSync( CHANGES_DIR )
		.filter( ( name ) => ! name.startsWith( '.' ) );

/**
 * @param {string} type    One of the CHANGE_TYPES keys.
 * @param {string} message Entry text.
 * @return {Promise<string[]>} Repository-relative paths of the entry files created.
 */
async function addEntryFile( type, message ) {
	if ( ! fs.existsSync( CHANGELOGGER ) ) {
		throw new Error(
			'vendor/bin/changelogger not found. Run `composer install` first.'
		);
	}

	const before = new Set( listEntryFiles() );
	await execFileP(
		CHANGELOGGER,
		[
			'add',
			'--no-interaction',
			// The default filename comes from the branch name, so a second entry on the same branch needs a suffix.
			'--filename-auto-suffix',
			// The entry file format requires a significance, but it is unused here: the release version
			// is passed explicitly when the changelog is compiled rather than derived from the entries.
			'--significance=patch',
			`--type=${ type.toLowerCase() }`,
			`--entry=${ message }`,
		],
		{ cwd: REPO_ROOT }
	);
	return listEntryFiles()
		.filter( ( name ) => ! before.has( name ) )
		.map( ( name ) => path.join( 'changelog', name ) );
}

/**
 * @param {string[]} argv Command-line arguments.
 * @return {{type?: string, entry?: string}} Values of --type and --entry, when given.
 */
function parseArgs( argv ) {
	const args = {};
	for ( const arg of argv ) {
		const match = arg.match( /^--(type|entry)=([\s\S]*)$/ );
		if ( ! match ) {
			throw new Error(
				`Unknown argument: ${ arg }. Usage: npm run changelog -- --type=<type> --entry="<message>"`
			);
		}
		args[ match[ 1 ] ] = match[ 2 ];
	}
	return args;
}

/**
 * @param {string} type Type as typed by the user, in any case.
 * @return {string} The matching CHANGE_TYPES key.
 */
function normalizeType( type ) {
	const match = Object.keys( CHANGE_TYPES ).find(
		( known ) => known.toLowerCase() === String( type ).toLowerCase()
	);
	if ( ! match ) {
		throw new Error(
			`Invalid type "${ type }". Expected one of: ${ Object.keys(
				CHANGE_TYPES
			).join( ', ' ) }`
		);
	}
	return match;
}

async function getBranchContext() {
	const [ log, diffstat ] = await Promise.all( [
		execFileP( 'git', [
			'log',
			`${ BASE_BRANCH }..HEAD`,
			'--format=%s%n%b',
		] ),
		execFileP( 'git', [ 'diff', `${ BASE_BRANCH }...HEAD`, '--stat' ] ),
	] );
	return {
		log: log.stdout.trim(),
		diffstat: diffstat.stdout.trim(),
	};
}

function runClaude( prompt ) {
	return new Promise( ( resolve, reject ) => {
		const proc = spawn( 'claude', [ '-p', '--model', 'haiku' ] );
		let stdout = '';
		let stderr = '';
		proc.stdout.on( 'data', ( d ) => {
			stdout += d;
		} );
		proc.stderr.on( 'data', ( d ) => {
			stderr += d;
		} );
		proc.on( 'error', ( err ) => {
			if ( err.code === 'ENOENT' ) {
				reject( new Error( 'claude CLI not found in PATH' ) );
			} else {
				reject( err );
			}
		} );
		proc.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				reject(
					new Error(
						stderr.trim() || `claude exited with code ${ code }`
					)
				);
			} else {
				resolve( stdout );
			}
		} );
		proc.stdin.end( prompt );
	} );
}

function extractJson( text ) {
	const fenced = text.match( /```(?:json)?\s*([\s\S]*?)```/ );
	const body = ( fenced ? fenced[ 1 ] : text ).trim();
	const start = body.indexOf( '{' );
	const end = body.lastIndexOf( '}' );
	if ( start === -1 || end === -1 ) {
		throw new Error(
			`no JSON object in response: ${ text.slice( 0, 200 ) }`
		);
	}
	return JSON.parse( body.slice( start, end + 1 ) );
}

function promptWithPrefill( question, prefill ) {
	return new Promise( ( resolve ) => {
		const rl = readline.createInterface( {
			input: process.stdin,
			output: process.stdout,
		} );
		rl.question( question, ( answer ) => {
			rl.close();
			resolve( answer );
		} );
		rl.write( prefill );
	} );
}

async function suggestWithClaude() {
	const { log, diffstat } = await getBranchContext();
	if ( ! log && ! diffstat ) {
		throw new Error( `no changes between ${ BASE_BRANCH } and HEAD` );
	}

	const typeList = Object.entries( CHANGE_TYPES )
		.map( ( [ t, d ] ) => `${ t } (${ d })` )
		.join( '; ' );

	const prompt = `Suggest a changelog entry for a WooCommerce Stripe plugin branch.

Respond with ONLY a JSON object on a single line, no prose, no code fences:
{"type": "<one of: ${ Object.keys( CHANGE_TYPES ).join(
		'|'
	) }>", "message": "<short imperative sentence>"}

Type meanings: ${ typeList }.
Message rules: concise, imperative voice, no trailing period, describes user-visible impact when possible.

Branch commits (${ BASE_BRANCH }..HEAD):
${ log || '(no commits)' }

Diff stat (${ BASE_BRANCH }...HEAD):
${ diffstat || '(no diff)' }
`;

	const raw = await runClaude( prompt );
	const parsed = extractJson( raw );

	if ( ! CHANGE_TYPES[ parsed.type ] ) {
		throw new Error( `invalid type "${ parsed.type }" from Claude` );
	}
	if ( typeof parsed.message !== 'string' || ! parsed.message.trim() ) {
		throw new Error( 'empty message from Claude' );
	}

	return {
		type: parsed.type,
		message: parsed.message.trim().replace( /\.$/, '' ),
	};
}

/* eslint-disable no-console */

async function promptForEntry() {
	const manualTypeChoices = Object.entries( CHANGE_TYPES ).map(
		( [ type, description ] ) => ( {
			name: `${ type } - ${ description }`,
			value: type,
		} )
	);

	const typeChoices = [
		...manualTypeChoices,
		{ name: '✨ Let Claude suggest an entry', value: CLAUDE_SUGGEST },
	];

	const first = await inquirer.prompt( [
		{
			type: 'list',
			name: 'changeType',
			message: 'Select the type of change:',
			choices: typeChoices,
		},
	] );

	let changeType;
	let message;

	if ( first.changeType === CLAUDE_SUGGEST ) {
		let suggestion = null;
		try {
			console.log( 'Asking Claude...' );
			suggestion = await suggestWithClaude();
			console.log(
				`Suggested: * ${ suggestion.type } - ${ suggestion.message }`
			);
		} catch ( err ) {
			console.warn(
				`Claude suggestion unavailable (${ err.message }). Falling back to manual entry.`
			);
		}

		let action = 'edit';
		if ( suggestion ) {
			const { next } = await inquirer.prompt( [
				{
					type: 'list',
					name: 'next',
					message: 'What would you like to do?',
					choices: [
						{ name: 'Use suggestion as-is', value: 'accept' },
						{ name: 'Edit before saving', value: 'edit' },
					],
					default: 'accept',
				},
			] );
			action = next;
		}

		if ( action === 'accept' ) {
			changeType = suggestion.type;
			message = suggestion.message;
		} else if ( suggestion ) {
			const typePattern = new RegExp(
				`^\\s*(${ Object.keys( CHANGE_TYPES ).join(
					'|'
				) })\\s*-\\s*(.+?)\\s*$`
			);
			while ( true ) {
				const line = await promptWithPrefill(
					'? Edit entry: ',
					`${ suggestion.type } - ${ suggestion.message }`
				);
				const match = line.match( typePattern );
				if ( match ) {
					changeType = match[ 1 ];
					message = match[ 2 ];
					break;
				}
				console.warn(
					`Invalid format. Expected "Type - message" where Type is one of: ${ Object.keys(
						CHANGE_TYPES
					).join( ', ' ) }`
				);
			}
		} else {
			const answers = await inquirer.prompt( [
				{
					type: 'list',
					name: 'changeType',
					message: 'Select the type of change:',
					choices: manualTypeChoices,
				},
				{
					type: 'input',
					name: 'message',
					message: 'Enter the changelog message:',
					validate: ( input ) =>
						input.trim().length > 0 || 'Message cannot be empty',
				},
			] );
			changeType = answers.changeType;
			message = answers.message;
		}
	} else {
		changeType = first.changeType;
		const answers = await inquirer.prompt( [
			{
				type: 'input',
				name: 'message',
				message: 'Enter the changelog message:',
				validate: ( input ) =>
					input.trim().length > 0 || 'Message cannot be empty',
			},
		] );
		message = answers.message;
	}

	return { changeType, message };
}

async function main() {
	try {
		const args = parseArgs( process.argv.slice( 2 ) );
		let changeType;
		let message;

		if ( args.type !== undefined || args.entry !== undefined ) {
			if ( ! args.type || ! args.entry || ! args.entry.trim() ) {
				throw new Error(
					'Both --type and --entry are required for non-interactive use'
				);
			}
			changeType = normalizeType( args.type );
			message = args.entry;
		} else {
			( { changeType, message } = await promptForEntry() );
		}

		// Remove trailing . from changelog message. See https://wp.me/pc4etw-1FS.
		message = message.trim().replace( /\.$/, '' );

		const created = await addEntryFile( changeType, message );
		console.log(
			`✅ Added changelog entry "* ${ changeType } - ${ message }" in ${ created.join(
				', '
			) }`
		);
		console.log(
			'Commit this file with your change. Do not edit changelog.txt or readme.txt directly.'
		);
	} catch ( error ) {
		console.error( '❌ Error:', error.message );
		process.exit( 1 );
	}
}

/* eslint-enable no-console */

main();
