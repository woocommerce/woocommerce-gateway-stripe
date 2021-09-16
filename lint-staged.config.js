module.exports = {
	'*.{js,jsx}': [ 'npm run format:js', 'eslint' ],
	'*.{scss,css}': [ 'npm run lint:css' ],
	'*.php':
		'./vendor/bin/phpcs --standard=phpcs.xml.dist --basepath=. --colors',
	'composer.json': 'composer validate --strict --no-check-all',
};
