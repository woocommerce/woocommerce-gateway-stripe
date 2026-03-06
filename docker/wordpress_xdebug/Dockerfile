FROM wordpress:php7.4
RUN pecl install xdebug-3.1.6 \
	&& echo 'xdebug.mode=off' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.start_with_request=yes' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.client_host=host.docker.internal' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.discover_client_host=1' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.client_port=9000' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.log=/tmp/xdebug.log' >> $PHP_INI_DIR/php.ini \
	&& docker-php-ext-enable xdebug
RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends gnupg2 subversion mariadb-client less jq
RUN apt-get install -y openssh-client
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& chmod +x wp-cli.phar \
	&& mv wp-cli.phar /usr/local/bin/wp
