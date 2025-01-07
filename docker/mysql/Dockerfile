FROM mariadb:latest
RUN apt-get update \
    && apt-get install --assume-yes --quiet --no-install-recommends vim
COPY my.cnf /etc/mysql/my.cnf
