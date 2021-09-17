module.exports = {
	'*.{js,jsx,ts,tsx}': [ 'npm run format:js', 'eslint' ],
	'*.{scss,css}': [ 'npm run lint:css' ],
	'*.php':
		'./vendor/bin/phpcs --standard=phpcs.xml.dist -n --basepath=. --colors',
	'composer.json': 'composer validate --strict --no-check-all',
};
