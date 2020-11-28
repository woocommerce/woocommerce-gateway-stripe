
#!/usr/bin/env bash

if [ -f "phpunit.phar" ]; then php phpunit.phar -c phpunit.xml; else ./vendor/bin/phpunit; fi;
