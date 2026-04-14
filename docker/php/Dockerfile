# ---- stage composer ----
FROM composer:2 AS composer_bin

# ---- stage vendor ----
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-scripts

# ---- app image ----
FROM dunglas/frankenphp:1-php8.5

RUN install-php-extensions intl pdo_pgsql pdo_sqlite sodium opcache gd zip
RUN if command -v apt-get >/dev/null 2>&1; then \
      apt-get update && apt-get install -y --no-install-recommends unzip p7zip-full openssh-client && rm -rf /var/lib/apt/lists/* ; \
    elif command -v apk >/dev/null 2>&1; then \
      apk add --no-cache unzip p7zip openssh-client ; \
    fi

COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY --from=vendor /app/vendor /app/vendor
COPY --link . /app

ENV APP_RUNTIME=Symfony\\Component\\Runtime\\SymfonyRuntime

COPY docker/php/Caddyfile /etc/caddy/Caddyfile

RUN mkdir -p var/cache var/log var/share \
    && printf '%s\n' \
        'APP_ENV=prod' \
        'APP_SECRET=build-secret' \
        'APP_SHARE_DIR=var/share' \
        'APP_2FA_ISSUER="Client Secrets Vault"' \
        'SYMFONY_TRUSTED_PROXIES=private_ranges' \
        'VAULT_ENCRYPTION_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef' \
        'DEFAULT_URI=http://secret-vault.localhost:8090' \
        'DATABASE_URL=postgresql://app:build-password@database:5432/client_secret_vault?serverVersion=16&charset=utf8' \
        'MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0' \
        'MAILER_DSN=null://null' \
        > /app/.env \
    && composer dump-autoload --classmap-authoritative --no-dev --no-interaction --no-scripts \
    && APP_ENV=prod APP_DEBUG=0 APP_SECRET=build-secret APP_SHARE_DIR=var/share APP_2FA_ISSUER='Client Secrets Vault' SYMFONY_TRUSTED_PROXIES='private_ranges' VAULT_ENCRYPTION_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef DEFAULT_URI='http://secret-vault.localhost:8090' DATABASE_URL='postgresql://app:build-password@database:5432/client_secret_vault?serverVersion=16&charset=utf8' MESSENGER_TRANSPORT_DSN='doctrine://default?auto_setup=0' MAILER_DSN='null://null' php bin/console importmap:install \
    && APP_ENV=prod APP_DEBUG=0 APP_SECRET=build-secret APP_SHARE_DIR=var/share APP_2FA_ISSUER='Client Secrets Vault' SYMFONY_TRUSTED_PROXIES='private_ranges' VAULT_ENCRYPTION_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef DEFAULT_URI='http://secret-vault.localhost:8090' DATABASE_URL='postgresql://app:build-password@database:5432/client_secret_vault?serverVersion=16&charset=utf8' MESSENGER_TRANSPORT_DSN='doctrine://default?auto_setup=0' MAILER_DSN='null://null' php bin/console asset-map:compile \
    && APP_ENV=prod APP_DEBUG=0 APP_SECRET=build-secret APP_SHARE_DIR=var/share APP_2FA_ISSUER='Client Secrets Vault' SYMFONY_TRUSTED_PROXIES='private_ranges' VAULT_ENCRYPTION_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef DEFAULT_URI='http://secret-vault.localhost:8090' DATABASE_URL='postgresql://app:build-password@database:5432/client_secret_vault?serverVersion=16&charset=utf8' MESSENGER_TRANSPORT_DSN='doctrine://default?auto_setup=0' MAILER_DSN='null://null' php bin/console cache:clear --env=prod --no-debug

EXPOSE 80
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
