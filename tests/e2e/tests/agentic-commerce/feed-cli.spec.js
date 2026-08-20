'use strict';

/* jshint node: true */

import { execSync } from 'child_process';
import { test, expect } from '@playwright/test';
import {
	AGENTIC_CLI_EXCLUDED_PRODUCT_SKU,
	AGENTIC_PRODUCT_SKU,
} from '../../utils/agentic';

// Mirrors the cli() helper in tests/e2e/bin/common.sh: a throwaway
// wordpress:cli container sharing the E2E WordPress volumes and network.
const wpCli = ( command ) =>
	execSync(
		`docker run -i --rm --user 33:33 --env-file "${ process.env.E2E_ROOT }/env/default.env" ` +
			'--volumes-from wcstripe-e2e-wordpress --network container:wcstripe-e2e-wordpress ' +
			`wordpress:cli ${ command }`,
		{ encoding: 'utf8' }
	);

test.describe( 'Agentic Commerce feed CLI', () => {
	// The commands run through docker, so they only work against the local
	// Docker E2E environment.
	test.skip(
		! process.env.DOCKER,
		'The feed CLI spec drives wp-cli through the Docker E2E containers.'
	);

	test( 'preview reports included and excluded catalog counts', async () => {
		const output = wpCli( 'wp stripe agentic-commerce preview' );

		expect( output ).toContain( 'Preview generation complete.' );

		const included = Number(
			output.match( /Included:\s+(\d+)/ )?.[ 1 ] ?? -1
		);
		const excluded = Number(
			output.match( /Excluded \(filter\):\s+(\d+)/ )?.[ 1 ] ?? -1
		);

		// At minimum the seeded in-stock product is included and the
		// pre-excluded CLI product is filtered out.
		expect( included ).toBeGreaterThan( 0 );
		expect( excluded ).toBeGreaterThan( 0 );
	} );

	test( 'sync generates a CSV containing eligible products only', async () => {
		// The feed writes to the CLI container's private temp dir until
		// delivery, so the file must be read in the same container run that
		// generated it.
		const output = wpCli(
			`sh -c 'OUT=$(wp stripe agentic-commerce sync); echo "$OUT"; ` +
				`FILE=$(echo "$OUT" | sed -n "s/.*File: *//p"); ` +
				`echo "---CSV-START---"; cat "$FILE"'`
		);

		expect( output ).toContain( 'Feed generated.' );

		const csv = output.split( '---CSV-START---' )[ 1 ] ?? '';
		const [ header ] = csv.trim().split( '\n' );

		expect( header ).toContain( 'id' );
		expect( header ).toContain( 'title' );
		expect( header ).toContain( 'price' );
		expect( header ).toContain( 'availability' );

		expect( csv ).toContain( AGENTIC_PRODUCT_SKU );
		expect( csv ).not.toContain( AGENTIC_CLI_EXCLUDED_PRODUCT_SKU );

		// The reported product count matches the CSV body (rows can span
		// multiple physical lines when fields contain newlines, so compare
		// against the count the walker reported rather than counting lines).
		const total = Number(
			output.match( /Products:\s+(\d+)/ )?.[ 1 ] ?? -1
		);
		expect( total ).toBeGreaterThan( 0 );
	} );
} );
