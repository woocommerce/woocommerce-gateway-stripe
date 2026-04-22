#!/usr/bin/env bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

COVERAGE_FLAGS="--no-coverage"
if [ "${XDEBUG_MODE_PHPUNIT:-}" = "coverage" ]; then
	COVERAGE_FLAGS="--coverage-cobertura php-coverage.xml"
fi

./vendor/bin/paratest \
	--configuration phpunit-parallel.xml.dist \
	$COVERAGE_FLAGS \
	"$@"
