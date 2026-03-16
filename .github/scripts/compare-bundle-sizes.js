#!/usr/bin/env node
'use strict';

const fs = require( 'fs' );

const [ , , basePath, headPath, outputPath ] = process.argv;

if ( ! basePath || ! headPath ) {
	console.error(
		'Usage: compare-bundle-sizes.js <base.json> <head.json> [output.md]'
	);
	process.exit( 1 );
}

const baseData = JSON.parse( fs.readFileSync( basePath, 'utf8' ) );
const headData = JSON.parse( fs.readFileSync( headPath, 'utf8' ) );

const baseRef = process.env.BASE_REF || 'base';
const headRef = process.env.HEAD_REF || 'head';

function toKB( bytes ) {
	return ( bytes / 1024 ).toFixed( 2 );
}

function getSign( delta ) {
	if ( delta > 0 ) {
		return '+';
	}
	// If the number is negative, it will already start with -.
	return '';
}

function formatDelta( delta ) {
	const kb = toKB( delta );
	const sign = getSign( delta );
	return `${ sign }${ kb } KB`;
}

function formatPercent( delta, base ) {
	if ( base === 0 ) {
		return 'N/A';
	}
	const pct = ( ( delta / base ) * 100 ).toFixed( 1 );
	const sign = getSign( delta );
	return `${ sign }${ pct }%`;
}

function statusIcon( file, delta ) {
	if ( ! baseData[ file ] ) {
		return '🆕';
	}
	if ( ! headData[ file ] ) {
		return '🗑️';
	}
	if ( delta > 0 ) {
		return '📈';
	}
	if ( delta < 0 ) {
		return '📉';
	}
	return '➡️';
}

const allFiles = [
	...new Set( [ ...Object.keys( baseData ), ...Object.keys( headData ) ] ),
].sort();

let totalBase = 0;
let totalHead = 0;
const rows = [];

for ( const file of allFiles ) {
	const base = baseData[ file ] ?? 0;
	const head = headData[ file ] ?? 0;
	const delta = head - base;
	totalBase += base;
	totalHead += head;

	const icon = statusIcon( file, delta );
	rows.push(
		`| ${ icon } \`${ file }\` | ${ toKB( base ) } KB | ${ toKB(
			head
		) } KB | ${ formatDelta( delta ) } | ${ formatPercent(
			delta,
			base
		) } |`
	);
}

const totalDelta = totalHead - totalBase;

const lines = [
	'<!-- bundle-size-report -->',
	'## 📦 Bundle Size Report',
	'',
	`Comparing \`${ headRef }\` → \`${ baseRef }\``,
	'',
	'| Bundle | Base | Head | Delta | Change |',
	'|--------|------|------|-------|--------|',
	...rows,
	`| **Total** | **${ toKB( totalBase ) } KB** | **${ toKB(
		totalHead
	) } KB** | **${ formatDelta( totalDelta ) }** | **${ formatPercent(
		totalDelta,
		totalBase
	) }** |`,
];

const output = lines.join( '\n' ) + '\n';

if ( outputPath ) {
	fs.writeFileSync( outputPath, output );
} else {
	process.stdout.write( output );
}
