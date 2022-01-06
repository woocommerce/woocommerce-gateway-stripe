const path = require( 'path' );
const { useE2EJestConfig } = require( '@woocommerce/e2e-environment' );

const { config } = require( 'dotenv' );

config( { path: path.resolve( __dirname, '.env' ) } );
config( { path: path.resolve( __dirname, 'local.env' ) } );

const jestConfig = useE2EJestConfig( {
	roots: [
		path.resolve( __dirname, '../specs/merchant' ),
		// path.resolve( __dirname, '../specs/shopper' ),
	],
} );

module.exports = jestConfig;
