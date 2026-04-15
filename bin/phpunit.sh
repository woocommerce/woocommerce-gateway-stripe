#!/usr/bin/env bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

./vendor/bin/paratest \
	--configuration phpunit-parallel.xml.dist \
	--no-coverage \
	"$@"
