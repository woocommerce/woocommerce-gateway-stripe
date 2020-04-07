# Testing instructions

## Running PHP Unit tests on a local machine

**Set up**

1. Install phpunit either globally or in the project with composer by running `composer install`.
2. Install and run mysql server either locally on in docker.
3. Run script `tests/bin/install.sh`:

	- Pass db accesss arguments as params e.g. `tests/bin/install.sh db_name user password db_host`.
	Example command if running DB in docker and port 5678 is exposed by docker container:
	```
	tests/bin/install.sh test_gateway root worpdress 127.0.0.1:5678
	```

	- If you don't have mysql installed on machine the script can fail due to missing `mysqladmin` command. As a workaround create new DB in phpMyAdmin manually and comment out create DB command in `install.sh`:
	```
	# mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
	```

**Running the tests**

If phpunit is installed with composer run the tests with command:
```
npm run test:php

or

./vendor/bin/phpunit -c phpunit.xml
```

If phpunit is installed globally run the tests with command:
```
phpunit -c phpunit.xml
```
