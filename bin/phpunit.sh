#!/usr/bin/env bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

if [ -f "phpunit.phar" ]; then php phpunit.phar -c phpunit-9.xml.dist; else ./vendor/bin/phpunit; fi;
