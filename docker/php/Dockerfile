FROM php:8.2-fpm

ARG UID=1000
ARG GID=1000
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev curl unzip default-mysql-client $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo_mysql zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

# Create a user with the host UID/GID and update php-fpm config
RUN if getent group "${GID}" >/dev/null; then \
        group_name="$(getent group "${GID}" | cut -d: -f1)"; \
        useradd -m -u "${UID}" -g "${GID}" -s /bin/bash appuser; \
    else \
        groupadd -g "${GID}" appuser && \
        useradd -m -u "${UID}" -g appuser -s /bin/bash appuser; \
        group_name="appuser"; \
    fi \
    && sed -i "s/^user = .*/user = appuser/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/^group = .*/group = ${group_name}/" /usr/local/etc/php-fpm.d/www.conf


COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/bin/sh", "/usr/local/bin/entrypoint.sh"]

WORKDIR /var/www/html
CMD ["php-fpm"]
