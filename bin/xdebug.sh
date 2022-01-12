#!/usr/bin/env bash

if [ "$#" -ne 1 ]; then
    SCRIPT_PATH=`basename "$0"`
    echo "Usage: $SCRIPT_PATH enable|disable"
    exit 1;
fi

SERVICE_ID=$(docker-compose ps -q wordpress)

if [ "$1" == "enable" ]; then
    docker exec -i $SERVICE_ID bash -c \
        '[ -f /usr/local/etc/php/disabled/docker-php-ext-xdebug.ini ] && cd /usr/local/etc/php/ && mv disabled/docker-php-ext-xdebug.ini conf.d/ || echo "Xdebug already enabled"'
else
    docker exec -i $SERVICE_ID bash -c \
        '[ -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini ] && cd /usr/local/etc/php/ && mkdir -p disabled/ && mv conf.d/docker-php-ext-xdebug.ini disabled/ || echo "Xdebug already disabled"'
fi

docker restart $SERVICE_ID

docker exec -i $SERVICE_ID bash -c '$(php -m | grep -q Xdebug) && echo "Status: Xdebug ENABLED" || echo "Status: Xdebug DISABLED"'