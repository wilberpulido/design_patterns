# PHP 8.4 = the version CI pins with setup-php (.github/workflows/ci.yml).
FROM php:8.4-cli

# The official PHP image ships without Composer: copy its binary from Composer's own image
# (multi-stage COPY) instead of downloading and verifying the installer by hand.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# unzip lets Composer extract --prefer-dist zips; without it, it falls back to slow git clones.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

# matiz: the image has NO php.ini, so PHP runs on built-in defaults. PHPUnit (strict config)
# warns when PHP is not configured for development — the same failure CI had with setup-php's
# production ini. Using the bundled development ini fixes it (display_errors, E_ALL, assertions).
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
