FROM wordpress:php8.4-apache

# System packages, Node.js 20, Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        openssh-client \
        curl \
        less \
        unzip \
        nano \
        ca-certificates \
        gnupg \
        default-mysql-client \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

# WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Allow WP-CLI to run as root (needed because remoteUser is root)
ENV WP_CLI_ALLOW_ROOT=1

# Xdebug (3.5.x is the current supported branch and works with PHP 8.4)
RUN pecl install xdebug-3.5.1 && docker-php-ext-enable xdebug
RUN printf "xdebug.mode=debug\nxdebug.start_with_request=yes\nxdebug.client_host=host.docker.internal\nxdebug.client_port=9003\nxdebug.log_level=0\n" \
    > /usr/local/etc/php/conf.d/xdebug.ini

# Coding agents
RUN npm install -g @anthropic-ai/claude-code @openai/codex

# Persistent shell history
RUN mkdir -p /shell_history \
    && touch /shell_history/.bash_history \
    && echo 'export HISTFILE=/shell_history/.bash_history' >> /root/.bashrc \
    && echo 'export PROMPT_COMMAND="history -a; $PROMPT_COMMAND"' >> /root/.bashrc
