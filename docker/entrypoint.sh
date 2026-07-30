#!/bin/sh
# E-Office Banyumas — container start-up.
#
# This is the home for the setup steps that used to be run by hand after a
# deployment. They belong here rather than in the Dockerfile because each one
# depends on state that only exists once the volumes are mounted and the
# environment is present — which is at run time, not at build time.
#
# NOT the home for migrations. `php artisan migrate --force` is deliberately NOT
# run here: it is a one-way change to shared data, and running it automatically
# on every container start means two replicas starting together race each other
# on the same schema. Migrations are an explicit, human-timed step — the README
# Deployment section spells it out.
#
# NOT TESTED YET — written without Docker available.

set -eu

echo "[entrypoint] menyiapkan kontainer E-Office..."

# ---------------------------------------------------------------------------
# 1. storage/ skeleton
#
# storage/ is a named volume. Docker seeds a NEW volume from the image, but a
# volume that already exists (an upgrade, or one created by hand) can be missing
# these directories, and Laravel's failure then is an unwritable-path stack
# trace rather than anything that names the cause.
# ---------------------------------------------------------------------------
mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# The volume mounts as root unless told otherwise; PHP-FPM runs as www-data and
# would not be able to write a single log line.
chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache

# ---------------------------------------------------------------------------
# 2. public/storage symlink
#
# Also created in the Dockerfile so the Nginx image has it. Re-asserted here
# because the target only exists once the storage volume is mounted, and because
# an operator who bind-mounts public/ over the image's copy would lose it.
# Uploaded banners and application icons are invisible without this link, and
# nothing errors — the images just silently 404.
# ---------------------------------------------------------------------------
if [ ! -L public/storage ]; then
    echo "[entrypoint] membuat symlink public/storage"
    ln -sfn ../storage/app/public public/storage
fi

# ---------------------------------------------------------------------------
# 3. Framework caches
#
# Built at start rather than baked into the image: config:cache freezes the
# ENVIRONMENT into a PHP file, so doing it at build time would bake in whatever
# values the build host happened to have — including an empty APP_KEY.
#
# These commands fail loudly if required configuration is missing, which is the
# behaviour we want: a container that will not start is far easier to diagnose
# than one that serves 500s.
#
# view:cache also compiles every Blade template, including the three Livewire
# single-file components whose filenames contain an emoji. If the emoji names
# are going to be a problem in a container, this is the step that will say so.
# ---------------------------------------------------------------------------
if [ "${EOFFICE_SKIP_CACHE:-0}" != "1" ]; then
    echo "[entrypoint] membangun cache konfigurasi, rute, dan view"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "[entrypoint] EOFFICE_SKIP_CACHE=1 — melewati pembangunan cache"
fi

echo "[entrypoint] siap. Menjalankan: $*"

exec "$@"
