// Define the config directory for the tests.
const path = require( 'path' );
process.env[ 'NODE_CONFIG_DIR' ] = path.resolve( __dirname, '../config/' );
export const config = require( 'config' );
