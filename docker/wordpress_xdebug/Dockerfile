FROM wordpress:php7.4
RUN pecl install xdebug-3.1.6 \
	&& echo 'xdebug.mode=off' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.start_with_request=yes' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.client_host=host.docker.internal' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.discover_client_host=1' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.client_port=9000' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.log=/tmp/xdebug.log' >> $PHP_INI_DIR/php.ini \
	&& docker-php-ext-enable xdebug
# Bullseye is EOL (2026-08-31): the live mirror's package index has gone
# inconsistent and 404s mid-install, and archive.debian.org has not imported
# the suite yet. Pin apt to snapshot.debian.org, Debian's immutable archive of
# every published package version, at a timestamp when bullseye was still fully
# patched. This is reproducible and independent of the live-mirror lifecycle
# (it won't break again when bullseye main is eventually pruned too). https
# authenticates the snapshot host so a network attacker can't replay an older
# signed snapshot; check-valid-until=no is scoped to these entries only (their
# Release files are intentionally old) rather than disabling freshness globally.
RUN printf '%s\n' \
	'deb [check-valid-until=no] https://snapshot.debian.org/archive/debian/20260830T000000Z/ bullseye main' \
	'deb [check-valid-until=no] https://snapshot.debian.org/archive/debian-security/20260830T000000Z/ bullseye-security main' \
	> /etc/apt/sources.list
RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends gnupg2 subversion mariadb-client less jq
RUN apt-get install -y openssh-client
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& chmod +x wp-cli.phar \
	&& mv wp-cli.phar /usr/local/bin/wp
