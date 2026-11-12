const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const {
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
} = require( '../changelog-build-file' );

const TYPE_LABELS = { add: 'Add', fix: 'Fix', dev: 'Dev' };

const CHANGELOG = [
	'*** Changelog ***',
	'',
	'xxxx-xx-xx - version 11.3.0',
	'* Fix - Unreleased fix',
	'* Add - Unreleased feature',
	'',
	'2026-11-10 - version 11.2.0',
	'* Fix - Released fix',
	'',
	'2026-10-16 - version 11.1.1',
	'* Dev - Older entry',
	'',
].join( '\n' );

const README = [
	'=== Plugin ===',
	'Stable tag: 11.2.0',
	'',
	'== Compatibility Notes ==',
	'* 11.2.0',
	'    - A note',
	'',
	'== Changelog ==',
	'',
	'= 11.2.0 - 2026-11-10 =',
	'',
	'**Important Fixes and Updates**',
	'',
	'* Fix - Released fix',
	'',
	'[See changelog for full details across versions](https://example.com/changelog.txt).',
	'',
].join( '\n' );

describe( 'changelog-build-file', () => {
	describe( 'entry files', () => {
		let changesDir;

		beforeEach( () => {
			changesDir = fs.mkdtempSync(
				path.join( os.tmpdir(), 'changelog-' )
			);
		} );

		afterEach( () => {
			fs.rmSync( changesDir, { recursive: true, force: true } );
		} );

		it( 'does not treat .gitkeep or other dotfiles as entries', () => {
			[ '.gitkeep', '.DS_Store', 'fix-b', 'add-a' ].forEach( ( name ) =>
				fs.writeFileSync( path.join( changesDir, name ), '' )
			);

			expect( listEntryFiles( changesDir ) ).toEqual( [
				'add-a',
				'fix-b',
			] );
		} );

		it( 'restores .gitkeep when the directory was emptied', () => {
			ensureKeepFile( changesDir );

			expect( fs.readdirSync( changesDir ) ).toEqual( [ '.gitkeep' ] );
		} );

		it( 'leaves an existing .gitkeep untouched', () => {
			fs.writeFileSync( path.join( changesDir, '.gitkeep' ), 'keep' );

			ensureKeepFile( changesDir );

			expect(
				fs.readFileSync( path.join( changesDir, '.gitkeep' ), 'utf8' )
			).toBe( 'keep' );
		} );
	} );

	describe( 'parseEntryFile', () => {
		it.each( [
			[
				'a standard entry',
				'Significance: patch\nType: fix\n\nFix a bug\n',
				{ type: 'fix', entry: 'Fix a bug' },
			],
			[
				'a comment header',
				'Significance: patch\nType: Dev\nComment: internal\n\nUpdate CI\n',
				{ type: 'dev', entry: 'Update CI' },
			],
			[
				'CRLF line endings',
				'Significance: patch\r\nType: add\r\n\r\nAdd a thing\r\n',
				{ type: 'add', entry: 'Add a thing' },
			],
			[
				'an entry with no text',
				'Significance: patch\nType: fix\n\n',
				{ type: 'fix', entry: '' },
			],
		] )( 'parses %s', ( description, content, expected ) => {
			expect( parseEntryFile( content ) ).toEqual( expected );
		} );
	} );

	describe( 'renderLine', () => {
		it.each( [
			[ 'Fix a bug', '* Fix - Fix a bug' ],
			[ 'Fix a bug.', '* Fix - Fix a bug' ],
			[ '  Fix a bug  ', '* Fix - Fix a bug' ],
		] )( 'renders %j', ( entry, expected ) => {
			expect( renderLine( 'Fix', entry ) ).toBe( expected );
		} );
	} );

	describe( 'findDuplicateEntries', () => {
		it.each( [
			[
				'an entry already released',
				{ type: 'fix', entry: 'Released fix' },
				true,
			],
			[
				'an entry that differs only by a trailing period',
				{ type: 'fix', entry: 'Released fix.' },
				true,
			],
			[
				'an entry already in the unreleased section',
				{ type: 'add', entry: 'Unreleased feature' },
				true,
			],
			[
				'the same text under another type',
				{ type: 'dev', entry: 'Released fix' },
				false,
			],
			[ 'a new entry', { type: 'fix', entry: 'Brand new fix' }, false ],
			[ 'an entry with no text', { type: 'fix', entry: '' }, false ],
		] )( 'for %s', ( description, entry, isDuplicate ) => {
			const duplicates = findDuplicateEntries(
				[ { name: 'entry-file', ...entry } ],
				TYPE_LABELS,
				CHANGELOG
			);

			expect( duplicates ).toEqual( isDuplicate ? [ 'entry-file' ] : [] );
		} );
	} );

	describe( 'getTopSection', () => {
		it( 'returns the newest section without surrounding blank lines', () => {
			expect( getTopSection( CHANGELOG ) ).toEqual( {
				date: 'xxxx-xx-xx',
				version: '11.3.0',
				lines: [
					'* Fix - Unreleased fix',
					'* Add - Unreleased feature',
				],
			} );
		} );

		it( 'returns null when there are no version sections', () => {
			expect( getTopSection( '*** Changelog ***\n' ) ).toBeNull();
		} );
	} );

	describe( 'getHistory', () => {
		it( 'returns everything from the section after the skipped ones', () => {
			expect( getHistory( CHANGELOG, 1 ) ).toBe(
				CHANGELOG.slice( CHANGELOG.indexOf( '2026-11-10' ) )
			);
		} );

		it( 'is unchanged by a new section added on top', () => {
			const released = CHANGELOG.replace(
				'xxxx-xx-xx - version 11.3.0',
				'2026-12-01 - version 11.3.0'
			);
			const compiled = released.replace(
				'*** Changelog ***\n\n',
				'*** Changelog ***\n\nxxxx-xx-xx - version 11.4.0\n* Fix - Next\n\n'
			);

			expect( getHistory( compiled, 1 ) ).toBe(
				getHistory( released, 0 )
			);
		} );

		it( 'is empty when there are not enough sections', () => {
			expect( getHistory( CHANGELOG, 3 ) ).toBe( '' );
		} );
	} );

	describe( 'shouldAmend', () => {
		it.each( [
			[
				'the top section is the same unreleased version',
				{ date: 'xxxx-xx-xx', version: '11.3.0' },
				true,
			],
			[
				'the top section is released',
				{ date: '2026-11-10', version: '11.2.0' },
				false,
			],
			[ 'the changelog has no sections', null, false ],
		] )( 'when %s', ( description, topSection, expected ) => {
			expect( shouldAmend( topSection, '11.3.0' ) ).toBe( expected );
		} );

		it( 'refuses to add a second unreleased section', () => {
			expect( () =>
				shouldAmend(
					{ date: 'xxxx-xx-xx', version: '11.3.0' },
					'11.4.0'
				)
			).toThrow( 'already has an unreleased section for 11.3.0' );
		} );
	} );

	describe( 'syncReadme', () => {
		const section = {
			date: 'xxxx-xx-xx',
			version: '11.3.0',
			lines: [ '* Fix - Unreleased fix', '* Add - Unreleased feature' ],
		};

		it( 'replaces the changelog section and keeps the rest of the readme', () => {
			expect( syncReadme( README, section ) ).toBe(
				[
					'=== Plugin ===',
					'Stable tag: 11.2.0',
					'',
					'== Compatibility Notes ==',
					'* 11.2.0',
					'    - A note',
					'',
					'== Changelog ==',
					'',
					'= 11.3.0 - xxxx-xx-xx =',
					'* Fix - Unreleased fix',
					'* Add - Unreleased feature',
					'',
					'[See changelog for full details across versions](https://example.com/changelog.txt).',
					'',
				].join( '\n' )
			);
		} );

		it( 'gives the same result when run twice', () => {
			const once = syncReadme( README, section );

			expect( syncReadme( once, section ) ).toBe( once );
		} );

		it.each( [
			[
				'the Changelog heading',
				README.replace( '== Changelog ==', '== History ==' ),
			],
			[
				'the full history link',
				README.replace(
					'[See changelog for full details',
					'[Full history'
				),
			],
		] )( 'throws when %s is missing', ( description, readme ) => {
			expect( () => syncReadme( readme, section ) ).toThrow(
				'readme.txt must contain'
			);
		} );
	} );

	describe( 'parseArgs', () => {
		it.each( [
			[ [ '--version=11.3.0' ], { version: '11.3.0', dryRun: false } ],
			[
				[ '--dry-run', '--version=11.3.0' ],
				{ version: '11.3.0', dryRun: true },
			],
		] )( 'parses %j', ( argv, expected ) => {
			expect( parseArgs( argv ) ).toEqual( expected );
		} );

		it.each( [
			[ 'no version', [] ],
			[ 'a partial version', [ '--version=11.3' ] ],
			[ 'a pre-release version', [ '--version=11.3.0-beta.1' ] ],
			[ 'an unknown argument', [ '--version=11.3.0', '--force' ] ],
		] )( 'rejects %s', ( description, argv ) => {
			expect( () => parseArgs( argv ) ).toThrow();
		} );
	} );
} );
