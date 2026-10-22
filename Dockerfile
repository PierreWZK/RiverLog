# syntax=docker/dockerfile:1
# ---------------------------------------------------------------------------
# RiverLog en production : FrankenPHP (Caddy + PHP 8.3) derrière le proxy de Coolify.
#
# Version exacte de PHP : elle garantit que l'image corrigée est bien utilisée (PHP2Shell, corrigé en 8.3.36).
# À chaque correctif de sécurité de PHP 8.3, monter ce numéro.
# Après chaque déploiement, Coolify lance : php artisan migrate --force && php artisan optimize
# Les réglages viennent des variables d'environnement de Coolify (voir README, « Mise en ligne »).
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.3.36

WORKDIR /app

# Extensions PHP : MySQL, archives .zip de la page Import, formats, cache d'opcodes
RUN install-php-extensions pdo_mysql zip intl opcache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-riverlog.ini"
COPY docker/Caddyfile /etc/caddy/Caddyfile

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Dépendances d'abord : cette couche reste en cache tant que composer.lock ne change pas
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY . .

RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
