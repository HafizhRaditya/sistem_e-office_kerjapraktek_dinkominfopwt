# syntax=docker/dockerfile:1
#
# E-Office Banyumas — production image.
#
# Four stages. `frontend` builds the Vite bundle with Node, `base` carries the
# PHP runtime and its extensions, `app` is the PHP-FPM image that actually runs
# the portal, and `web` is an Nginx image holding a copy of public/.
#
# Node exists only in the `frontend` stage. The final images never inherit from
# it, so neither Node nor node_modules reaches the server — the built assets are
# copied across as plain files.
#
# NOT BUILT OR TESTED YET. Written on a machine without Docker installed; every
# stage below is reasoned from composer.json, package.json and vite.config.js
# rather than from a successful build. Run `docker compose build` and fix what
# it reports before trusting this on the Dinkominfo server.

# ---------------------------------------------------------------------------
# Stage 1 — frontend assets (Node is thrown away after this)
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS frontend

# UTF-8 throughout. Three Livewire single-file components are named with a
# lightning-bolt emoji (U+26A1) — resources/views/components/admin/⚡*.blade.php.
# That is Livewire 4's own convention for single-file components and it works
# locally, but a build running under the POSIX/C locale can mangle non-ASCII
# filenames when copying or globbing them. Vite only reads resources/css and
# resources/js, so this stage should not touch those files at all; the locale is
# pinned anyway because the cost is nil and the failure would be baffling.
ENV LANG=C.UTF-8 \
    LC_ALL=C.UTF-8

WORKDIR /app

# Dependency layer first, so it is only rebuilt when the lockfile moves.
# .npmrc is copied with them because it sets ignore-scripts=true, which changes
# what `npm ci` does.
COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

# NOTE — this step needs OUTBOUND NETWORK ACCESS at build time. vite.config.js
# uses laravel-vite-plugin's bunny('Inter') helper, which downloads the font
# files from fonts.bunny.net while building. On an air-gapped build host this
# step fails. If the Dinkominfo build server has no outbound access, the font
# helper has to be replaced with locally committed font files first.
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 — PHP runtime shared by the app image
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS base

ENV LANG=C.UTF-8 \
    LC_ALL=C.UTF-8

# Extensions.
#
# `composer check-platform-reqs --no-dev` reports these hard requirements:
#   ctype dom fileinfo filter hash iconv json libxml openssl pcre session
#   tokenizer mbstring
# All but mbstring are already compiled into the official image. mbstring is
# satisfied by symfony/polyfill-mbstring on paper, but the real extension is
# considerably faster and Blade leans on mb_* everywhere, so it is installed.
#
# Composer cannot see these, and they are added deliberately:
#   pdo_pgsql/pgsql — required at RUNTIME. PostgreSQL is the only database this
#                     project supports; composer has no way to know that.
#   zip             — composer itself uses it to unpack dist packages at build.
#   intl            — locale-aware string handling (APP_LOCALE=id).
#   gd              — for image uploads. Note honestly: nothing in the app
#                     currently PROCESSES images (uploads are validated with
#                     Laravel's `image` rule, which uses getimagesize() and
#                     fileinfo from core, then stored as-is). This is
#                     precautionary, and can be dropped to slim the image.
#   opcache         — production bytecode cache; configured below.
#
# The -dev headers are deliberately NOT purged afterwards. `apt-get purge
# --auto-remove` on these is a well-known way to break a PHP image: the runtime
# libraries (libpq5, libicu, …) come in as automatically-installed dependencies
# of the -dev packages, so auto-remove can take them out from under the
# extensions that were just compiled against them. Removing them safely needs
# the apt-mark dance the official image uses, and that is not something to write
# blind on a machine where the result cannot be built and tested. A larger image
# is the right trade against an image that starts and then fails on the first
# database query.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        gd \
        mbstring \
        opcache \
    ; \
    rm -rf /var/lib/apt/lists/*

# Production opcache. validate_timestamps=0 means the cache is never checked
# against the filesystem, which is right for an immutable image and wrong for
# development — a code change would simply not be seen without a restart.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.interned_strings_buffer=16'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Uploads are validated at max:5120 (5 MB) in the controllers. PHP's own limits
# have to be at least that, or the request dies before validation can explain
# why. Left a little above 5 MB to allow for multipart overhead.
RUN { \
        echo 'upload_max_filesize=8M'; \
        echo 'post_max_size=12M'; \
        echo 'memory_limit=256M'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/eoffice.ini

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Stage 3 — the application image (PHP-FPM)
# ---------------------------------------------------------------------------
FROM base AS app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# Dependencies before source, so editing a controller does not re-resolve the
# whole tree. --no-scripts is required here: the post-autoload-dump script runs
# `artisan package:discover`, which needs application files that have not been
# copied yet.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-progress

# Application source (filtered by .dockerignore).
COPY . .

# Built assets from stage 1. public/build is gitignored, so it exists only
# because the frontend stage made it.
COPY --from=frontend /app/public/build ./public/build

# Now that the source is present, generate the optimised classmap. This also
# fires the post-autoload-dump script (artisan package:discover) — composer runs
# it automatically, so calling it separately would discover packages twice.
#
# --optimize only, NOT --classmap-authoritative. Authoritative mode removes the
# filesystem fallback entirely, and Livewire 4 compiles its single-file
# components into generated classes at runtime; taking the fallback away is a
# risk with nothing to gain here, and not one that can be assessed without a
# build to test against.
RUN composer dump-autoload --no-dev --optimize

# storage/ is replaced by a named volume at runtime, but the skeleton must exist
# in the image: Docker seeds a fresh named volume from the image's contents, so
# these directories are what the volume starts life with. Without them Laravel
# fails on first boot with an unwritable framework/views.
RUN set -eux; \
    mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R u+rwX,g+rwX storage bootstrap/cache

# The public/storage symlink is created here rather than by `artisan
# storage:link` at runtime, so the same link exists in BOTH this image and the
# Nginx image. Its target lives on the shared storage volume, so it resolves
# once that volume is mounted. Uploaded banners and application icons are served
# through it.
RUN ln -sfn ../storage/app/public public/storage

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 4 — Nginx, holding its own copy of public/
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

# Nginx serves static files itself and hands .php to PHP-FPM over the network,
# so it needs the public/ tree — including the storage symlink built above.
# Copying it (rather than sharing a volume with the app container) means a
# rebuild can never leave Nginx serving assets from a previous release.
COPY --from=app /var/www/html/public /var/www/html/public

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
