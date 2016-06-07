/* jshint node:true */
module.exports = function( grunt ){
	'use strict';

	grunt.initConfig({
		shell: {
			options: {
				stdout: true,
				stderr: true
			},
			generatepot: {
				command: [
					'makepot'
				].join( '&&' )
			}
		},
	});

	// Load NPM tasks to be used here
	grunt.loadNpmTasks( 'grunt-shell' );

	// Register tasks
	grunt.registerTask( 'default', [
		'shell:generatepot'
	]);

};
