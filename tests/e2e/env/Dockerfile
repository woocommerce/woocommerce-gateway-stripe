FROM wordpress:php7.4
RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends gnupg2 subversion mariadb-client less jq
RUN apt-get install -y openssh-client
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& chmod +x wp-cli.phar \
	&& mv wp-cli.phar /usr/local/bin/wp
